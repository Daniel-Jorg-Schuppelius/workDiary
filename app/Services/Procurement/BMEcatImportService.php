<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BMEcatImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\SupplierCatalogSource;
use CommonToolkit\Helper\Data\{NumberHelper, XmlHelper};
use RuntimeException;
use SimpleXMLElement;

/**
 * Importiert einen BMEcat-Produktkatalog (XML, 1.2 oder 2005) in die
 * Katalogartikel einer Quelle (Feature 050, „Später": strukturierte
 * Katalogformate). Liest Artikel-/Produktelemente und übergibt sie als
 * normalisierte Datensätze dem {@see CatalogItemUpserter}. Behandelt die Datei
 * als vollständigen Katalog-Snapshot (nicht enthaltene Artikel werden
 * abgekündigt). Beide Element-Konventionen werden unterstützt:
 *  - BMEcat 1.2:  ARTICLE / SUPPLIER_AID / ARTICLE_DETAILS
 *  - BMEcat 2005: PRODUCT / SUPPLIER_PID / PRODUCT_DETAILS
 */
class BMEcatImportService {
    public function __construct(private readonly CatalogItemUpserter $upserter = new CatalogItemUpserter()) {}

    /**
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     *
     * @throws RuntimeException Bei ungültigem XML oder ohne Artikelelemente.
     */
    public function import(SupplierCatalogSource $source, string $content): array {
        $records = $this->parse($content);
        if ($records === []) {
            throw new RuntimeException((string) __('procurement.catalog.error.no_articles'));
        }

        return $this->upserter->persist($source, $records, $content);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(string $content): array {
        // Feature 052: XXE-sicheres Laden über das Common-Toolkit (LIBXML_NONET).
        $xml = XmlHelper::safeLoadString($content);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException((string) __('procurement.catalog.error.invalid_xml'));
        }

        /** @var list<SimpleXMLElement> $articles */
        $articles = $xml->xpath('//*[local-name()="ARTICLE" or local-name()="PRODUCT"]') ?: [];

        $records = [];
        foreach ($articles as $article) {
            $externalNo = $this->text($article, ['SUPPLIER_AID', 'SUPPLIER_PID']);
            if ($externalNo === '') {
                continue;
            }

            $details = $article->ARTICLE_DETAILS ?? $article->PRODUCT_DETAILS ?? null;
            $name = $details instanceof SimpleXMLElement ? $this->text($details, ['DESCRIPTION_SHORT']) : '';
            $description = $details instanceof SimpleXMLElement ? $this->text($details, ['DESCRIPTION_LONG']) : '';
            $gtin = $details instanceof SimpleXMLElement ? $this->text($details, ['EAN']) : '';
            $manufacturerNo = $details instanceof SimpleXMLElement ? $this->text($details, ['MANUFACTURER_AID', 'MANUFACTURER_PID']) : '';
            $manufacturer = $details instanceof SimpleXMLElement ? $this->text($details, ['MANUFACTURER_NAME']) : '';

            [$price, $currency] = $this->price($article);
            $classSystem = $this->firstNode($article, 'REFERENCE_FEATURE_SYSTEM_NAME');
            $classCode = $this->firstNode($article, 'REFERENCE_FEATURE_GROUP_ID');

            $records[] = [
                'external_no' => $externalNo,
                'name' => $name !== '' ? $name : $externalNo,
                'description' => $description !== '' ? $description : null,
                'gtin' => $gtin !== '' ? $gtin : null,
                'manufacturer_no' => $manufacturerNo !== '' ? $manufacturerNo : null,
                'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                'classification_system' => $classSystem !== '' ? $classSystem : null,
                'classification_code' => $classCode !== '' ? $classCode : null,
                'image_url' => $this->mime($article, ['normal', 'thumbnail', 'detail']),
                'datasheet_url' => $this->mime($article, ['data_sheet', 'safety_data_sheet', 'datasheet']),
                'purchase_price' => $price,
                'currency' => $currency,
                'pack_size' => '1',
                'base_qty' => '1',
                'tiers' => $this->tiers($article),
            ];
        }

        return $records;
    }

    /**
     * Liest den ersten gefundenen Einkaufspreis (PRICE_AMOUNT) samt Währung.
     *
     * @return array{0: numeric-string|null, 1: string}
     */
    private function price(SimpleXMLElement $article): array {
        $amounts = $article->xpath('.//*[local-name()="PRICE_AMOUNT"]') ?: [];
        $first = $amounts[0] ?? null;
        if (! $first instanceof SimpleXMLElement) {
            return [null, 'EUR'];
        }

        $price = $this->scaledDecimal(trim((string) $first));

        $currencyNodes = $article->xpath('.//*[local-name()="PRICE_CURRENCY"]') ?: [];
        $currency = isset($currencyNodes[0]) ? trim((string) $currencyNodes[0]) : '';

        return [$price, $currency !== '' ? $currency : 'EUR'];
    }

    /**
     * Erstes nicht-leeres Kindelement aus einer Namensliste (case-insensitive).
     *
     * @param  list<string>  $names
     */
    private function text(SimpleXMLElement $node, array $names): string {
        foreach ($names as $name) {
            $value = trim((string) $node->{$name});
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Mengenstaffeln aus den ARTICLE_PRICE/PRODUCT_PRICE-Elementen (LOWER_BOUND > 1).
     * Der Basispreis (Bound ≤ 1) steht am Artikel und wird hier ausgelassen.
     *
     * @return list<array{min_qty: string, unit_price: string}>
     */
    private function tiers(SimpleXMLElement $article): array {
        $prices = $article->xpath('.//*[local-name()="ARTICLE_PRICE" or local-name()="PRODUCT_PRICE"]') ?: [];
        $tiers = [];
        foreach ($prices as $price) {
            $amountNodes = $price->xpath('.//*[local-name()="PRICE_AMOUNT"]') ?: [];
            $boundNodes = $price->xpath('.//*[local-name()="LOWER_BOUND"]') ?: [];
            $unitPrice = $this->scaledDecimal(trim((string) ($amountNodes[0] ?? '')));
            $minQty = $this->scaledDecimal(trim((string) ($boundNodes[0] ?? '1')));
            if ($unitPrice === null || $minQty === null || bccomp($minQty, '1', 4) <= 0) {
                continue;
            }
            $tiers[] = [
                'min_qty' => $minQty,
                'unit_price' => $unitPrice,
            ];
        }

        return $tiers;
    }

    /**
     * Normalisiert einen Roh-Zahlwert präzisionswahrend (ohne float-Roundtrip)
     * auf einen Decimal-String mit 4 Nachkommastellen; Nicht-Zahlen → null.
     * bcadd allein würde trunkieren — der Halbschritt erzwingt kaufmännische
     * Rundung (half-up, deterministisch statt float-repräsentationsabhängig).
     * Exponentialnotation geht weiter über den float-Pfad, da bcmath sie nicht
     * versteht und Lieferanten-Exporte sie liefern können.
     *
     * @return numeric-string|null
     */
    private function scaledDecimal(string $raw): ?string {
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw) && stripos($raw, 'e') !== false) {
            return number_format((float) $raw, 4, '.', '');
        }
        $normalized = NumberHelper::normalizeDecimalString($raw);
        // is_numeric() entfällt: normalizeDecimalString() setzt numeric-string
        // durch. Die Exponential-Prüfung bleibt — bcadd() unten kann keine
        // E-Notation verarbeiten.
        if (stripos($normalized, 'e') !== false) {
            return null;
        }
        $halfStep = str_starts_with($normalized, '-') ? '-0.00005' : '0.00005';

        return bcadd($normalized, $halfStep, 4);
    }

    /** Erster Wert eines Elements (an beliebiger Stelle) innerhalb des Artikels. */
    private function firstNode(SimpleXMLElement $article, string $name): string {
        $nodes = $article->xpath('.//*[local-name()="' . $name . '"]') ?: [];

        return isset($nodes[0]) ? trim((string) $nodes[0]) : '';
    }

    /**
     * Erste MIME_SOURCE-URL, deren MIME_PURPOSE zu einem der Zwecke passt.
     *
     * @param  list<string>  $purposes
     */
    private function mime(SimpleXMLElement $article, array $purposes): ?string {
        $mimes = $article->xpath('.//*[local-name()="MIME"]') ?: [];
        foreach ($mimes as $mime) {
            $purposeNodes = $mime->xpath('.//*[local-name()="MIME_PURPOSE"]') ?: [];
            $sourceNodes = $mime->xpath('.//*[local-name()="MIME_SOURCE"]') ?: [];
            $purpose = strtolower(trim((string) ($purposeNodes[0] ?? '')));
            $source = trim((string) ($sourceNodes[0] ?? ''));
            if ($source !== '' && in_array($purpose, $purposes, true)) {
                return $source;
            }
        }

        return null;
    }
}
