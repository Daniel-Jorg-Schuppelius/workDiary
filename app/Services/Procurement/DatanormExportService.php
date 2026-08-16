<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatanormExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{Article, ArticleVariant, Organization};
use App\Models\B2b\{B2bCatalogAccess, B2bCatalogItem};
use App\Support\UnitCodeMapper;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use DateTimeImmutable;
use ERechnungToolkit\Entities\Datanorm\{DatanormArticle, DatanormCatalog, DatanormCustomer, DatanormPriceChange, DatanormTextBlock};
use ERechnungToolkit\Enums\{DatanormDataMark, DatanormPriceIndicator, DatanormVersion};
use ERechnungToolkit\Generators\DatanormGenerator;

/**
 * Exportiert den verkäuflichen Artikelstamm als DATANORM-Katalog (Feature
 * 107, W5): Kunden der Organisation erhalten DATANORM.001 + DATAINFO.TXT als
 * Dateipaket in Version 4 oder 5. Varianten werden als eigene Artikelnummern
 * (SKU) ausgegeben, Beschreibungen als Langtextbausteine (Textkennzeichen 4 =
 * Kurztexte + Langtext), Einheiten über den {@see UnitCodeMapper}.
 *
 * Preisquelle ist der VK (`default_sale_price` bzw. Varianten-`sale_price`) —
 * wahlweise als rabattfähiger Listenpreis (Kennzeichen 1) oder als Nettopreis
 * (Kennzeichen 2). Artikelnummern über 15 Zeichen lässt DATANORM nicht zu;
 * solche Artikel werden übersprungen und gemeldet.
 */
class DatanormExportService {
    public function __construct(private readonly DatanormGenerator $generator = new DatanormGenerator) {}

    /**
     * @return array{files: array<string, string>, articles: int, skipped: list<string>}
     */
    public function export(Organization $organization, DatanormVersion $version, DatanormPriceIndicator $priceIndicator): array {
        ['catalog' => $catalog, 'skipped' => $skipped] = $this->buildCatalog($organization, $version, $priceIndicator);

        $files = ['DATANORM.001' => $this->generator->generateArticleFile($catalog, $version)];
        if ($catalog->getProductGroups() !== []) {
            // W8: Warengruppen aus der Artikel-Kategorie → eigene WRG-Datei.
            $files['DATANORM.WRG'] = $this->generator->generateProductGroupFile($catalog, $version);
        }
        if ($catalog->getDiscountGroups() !== []) {
            // W9: Verkaufs-Rabattgruppen → RAB-Datei (nur beim Listenpreis-Export).
            $files['DATANORM.RAB'] = $this->generator->generateDiscountGroupFile($catalog, $version);
        }
        $files['DATAINFO.TXT'] = $this->dataInfo($organization, $version, 'DATANORM.001', count($catalog->getArticles()), $skipped);

        return ['files' => $files, 'articles' => count($catalog->getArticles()), 'skipped' => $skipped];
    }

    /**
     * DATPREIS-Preisdatei (Feature 107, W6): der aktuelle Preisstand als
     * P-Sätze. Ohne Zugang der allgemeine VK aller verkäuflichen Artikel;
     * mit {@see B2bCatalogAccess} nur die freigegebenen Artikel des Kunden zu
     * dessen effektiven Nettopreisen (Feature 099, `custom_price`), inklusive
     * K-Kontrollsatz mit der Kundennummer.
     *
     * @return array{files: array<string, string>, articles: int, skipped: list<string>}
     */
    public function exportPrices(Organization $organization, DatanormVersion $version, DatanormPriceIndicator $priceIndicator, ?B2bCatalogAccess $access = null, ?\DateTimeInterface $since = null): array {
        $creator = $this->creatorData($organization);
        $catalog = new DatanormCatalog(
            version: $version,
            dataMark: DatanormDataMark::PriceChanges,
            creationDate: new DateTimeImmutable('today'),
            currency: CurrencyCode::Euro,
            description: mb_substr($organization->name . ' Preisdatei', 0, 40),
            copyright: 'Copyright ' . $organization->name,
            creatorShortName: mb_substr($organization->name, 0, 13),
            creatorName: $creator['name'],
            creatorStreet: $creator['street'],
            creatorCountry: $creator['country'],
            creatorZip: $creator['zip'],
            creatorCity: $creator['city'],
            infoText: $organization->name . ' Preisdatei'
        );

        $skipped = [];
        if ($access !== null) {
            $customer = $access->customer;
            if ($customer !== null) {
                $catalog->setCustomer(new DatanormCustomer(
                    customerNumber: trim((string) $customer->number),
                    name: trim((string) ($customer->company ?: $customer->name)) ?: null,
                    street: $customer->address_street,
                    zip: $customer->address_zip,
                    city: $customer->address_city
                ));
            }
            // Kundenindividuelle Preise sind immer Nettopreise.
            $access->items()->with('article')->get()->each(function (B2bCatalogItem $item) use ($catalog, $version, &$skipped): void {
                $article = $item->article;
                $price = $item->effectivePrice();
                if ($article === null || $price === null) {
                    return;
                }
                $this->appendPriceChange($catalog, $version, $skipped, (string) $article->number, DatanormPriceIndicator::NetPrice, $price);
            });
        } else {
            // W10: „Änderungen seit Datum" — nur Artikel/Varianten, deren VK
            // laut Preisverlauf seit dem Stichtag gesetzt oder geändert wurde.
            $changed = null;
            if ($since !== null) {
                $changed = \App\Models\ArticleSalePriceHistory::query()
                    ->where('organization_id', $organization->id)
                    ->where('recorded_at', '>=', $since)
                    ->get(['article_id', 'article_variant_id']);
            }
            foreach ($this->exportableArticles($organization) as $entry) {
                if ($entry['price'] === null) {
                    continue;
                }
                if ($changed !== null) {
                    $hit = $entry['variant_id'] !== null
                        ? $changed->contains(fn ($h) => (int) $h->article_variant_id === $entry['variant_id'])
                        : $changed->contains(fn ($h) => (int) $h->article_id === $entry['article_id'] && $h->article_variant_id === null);
                    if (! $hit) {
                        continue;
                    }
                }
                $this->appendPriceChange($catalog, $version, $skipped, $entry['number'], $priceIndicator, $entry['price']);
            }
        }

        $files = [
            'DATPREIS.001' => $this->generator->generatePriceFile($catalog, $version),
            'DATAINFO.TXT' => $this->dataInfo($organization, $version, 'DATPREIS.001', count($catalog->getPriceChanges()), $skipped),
        ];

        return ['files' => $files, 'articles' => count($catalog->getPriceChanges()), 'skipped' => $skipped];
    }

    /**
     * @param  list<string>  $skipped
     */
    private function appendPriceChange(DatanormCatalog $catalog, DatanormVersion $version, array &$skipped, string $number, DatanormPriceIndicator $priceIndicator, Money $price): void {
        if (mb_strlen($number) > 15) {
            $skipped[] = $number;

            return;
        }
        $catalog->addPriceChange(new DatanormPriceChange(
            articleNumber: $number,
            priceIndicator: $priceIndicator,
            price: $price->withScale(2),
            priceUnitAmount: $version === DatanormVersion::V5 ? 1 : null
        ));
    }

    /**
     * Verkäufliche aktive Artikel als Nummer/Preis-Paare (Varianten als
     * eigene Nummern) — gemeinsame Quelle für Katalog- und Preisexport.
     *
     * @return list<array{number: string, price: Money|null, article_id: int, variant_id: int|null}>
     */
    private function exportableArticles(Organization $organization): array {
        $entries = [];
        $articles = Article::query()
            ->where('organization_id', $organization->id)
            ->where('sellable', true)
            ->where('status', \App\Enums\Article\ArticleStatus::Active)
            ->with(['variants'])
            ->orderBy('number')
            ->get();

        foreach ($articles as $article) {
            $variants = $article->variants->filter(static fn (ArticleVariant $v): bool => $v->sku !== null && $v->sku !== '');
            if ($variants->isEmpty()) {
                $entries[] = ['number' => (string) $article->number, 'price' => $article->default_sale_price, 'article_id' => (int) $article->id, 'variant_id' => null];

                continue;
            }
            foreach ($variants as $variant) {
                $entries[] = ['number' => (string) $variant->sku, 'price' => $variant->sale_price ?? $article->default_sale_price, 'article_id' => (int) $article->id, 'variant_id' => (int) $variant->id];
            }
        }

        return $entries;
    }

    /**
     * @return array{catalog: DatanormCatalog, skipped: list<string>}
     */
    private function buildCatalog(Organization $organization, DatanormVersion $version, DatanormPriceIndicator $priceIndicator): array {
        $skipped = [];
        $creator = $this->creatorData($organization);

        $catalog = new DatanormCatalog(
            version: $version,
            dataMark: DatanormDataMark::Articles,
            creationDate: new DateTimeImmutable('today'),
            currency: CurrencyCode::Euro,
            description: mb_substr($organization->name . ' Katalog', 0, 40),
            copyright: 'Copyright ' . $organization->name,
            creatorShortName: mb_substr($organization->name, 0, 13),
            creatorName: $creator['name'],
            creatorStreet: $creator['street'],
            creatorCountry: $creator['country'],
            creatorZip: $creator['zip'],
            creatorCity: $creator['city'],
            infoText: $organization->name . ' Katalog'
        );

        $textCounter = 0;
        $articles = Article::query()
            ->where('organization_id', $organization->id)
            ->where('sellable', true)
            ->where('status', \App\Enums\Article\ArticleStatus::Active)
            ->with(['variants', 'salesDiscountGroup'])
            ->orderBy('number')
            ->get();

        // W8: Warengruppen-Codes aus den Artikel-Kategorien ableiten (DATANORM
        // erlaubt A3/A10-Codes; die Klartexte reisen in der WRG-Datei mit).
        $groups = $this->productGroupCodes($articles);
        foreach ($groups['main'] as $label => $code) {
            $catalog->addProductGroup(new \ERechnungToolkit\Entities\Datanorm\DatanormProductGroup($code, null, $label));
        }
        foreach ($groups['sub'] as $mainLabel => $subs) {
            foreach ($subs as $subLabel => $subCode) {
                $catalog->addProductGroup(new \ERechnungToolkit\Entities\Datanorm\DatanormProductGroup($groups['main'][$mainLabel], $subCode, $subLabel));
            }
        }

        foreach ($articles as $article) {
            $mainCode = $article->category !== null ? ($groups['main'][trim((string) $article->category)] ?? null) : null;
            $subCode = $mainCode !== null && $article->subcategory !== null
                ? ($groups['sub'][trim((string) $article->category)][trim((string) $article->subcategory)] ?? null)
                : null;

            // W9: Verkaufs-Rabattgruppe nur beim Listenpreis-Export — Empfänger
            // rechnen Liste − Rabatt; die Gruppe reist in der RAB-Datei mit.
            $discountCode = null;
            $salesGroup = $article->salesDiscountGroup;
            if ($priceIndicator === DatanormPriceIndicator::ListPrice && $salesGroup !== null) {
                $discountCode = $salesGroup->code;
                if ($catalog->getDiscountGroup($salesGroup->code) === null) {
                    $catalog->addDiscountGroup(new \ERechnungToolkit\Entities\Datanorm\DatanormDiscountGroup(
                        $salesGroup->code,
                        match ($salesGroup->kind) {
                            \App\Models\SalesDiscountGroup::KIND_FACTOR => \ERechnungToolkit\Enums\DatanormDiscountKind::Factor,
                            \App\Models\SalesDiscountGroup::KIND_SURCHARGE => \ERechnungToolkit\Enums\DatanormDiscountKind::Surcharge,
                            default => \ERechnungToolkit\Enums\DatanormDiscountKind::Discount,
                        },
                        (float) $salesGroup->value,
                        $salesGroup->label
                    ));
                }
            }

            $variants = $article->variants->filter(static fn (ArticleVariant $v): bool => $v->sku !== null && $v->sku !== '');
            if ($variants->isEmpty()) {
                $this->appendArticle($catalog, $version, $priceIndicator, $textCounter, $skipped, (string) $article->number, $article->name, $article->description, $article->base_unit, $article->default_sale_price, $article->gtin, $mainCode, $subCode, $discountCode);

                continue;
            }
            foreach ($variants as $variant) {
                $this->appendArticle(
                    $catalog,
                    $version,
                    $priceIndicator,
                    $textCounter,
                    $skipped,
                    (string) $variant->sku,
                    trim($article->name . ' ' . $variant->option_signature),
                    $article->description,
                    $article->base_unit,
                    $variant->sale_price ?? $article->default_sale_price,
                    $variant->gtin ?? $article->gtin,
                    $mainCode,
                    $subCode,
                    $discountCode
                );
            }
        }

        return ['catalog' => $catalog, 'skipped' => $skipped];
    }

    /**
     * Deterministische Warengruppen-Codes aus den Kategorie-Klartexten:
     * Hauptwarengruppe max. 3 Zeichen, Warengruppe max. 10 (DATANORM-Feldmaße),
     * Kollisionen erhalten Ziffern-Suffixe.
     *
     * @param  \Illuminate\Support\Collection<int, Article>  $articles
     * @return array{main: array<string, string>, sub: array<string, array<string, string>>}
     */
    private function productGroupCodes($articles): array {
        $mainLabels = [];
        $subLabels = [];
        foreach ($articles as $article) {
            $main = trim((string) $article->category);
            if ($main === '') {
                continue;
            }
            $mainLabels[$main] = true;
            $sub = trim((string) $article->subcategory);
            if ($sub !== '') {
                $subLabels[$main][$sub] = true;
            }
        }
        ksort($mainLabels);

        $main = [];
        $usedMain = [];
        foreach (array_keys($mainLabels) as $label) {
            $main[$label] = $this->uniqueGroupCode($label, 3, $usedMain);
        }

        $sub = [];
        foreach ($subLabels as $mainLabel => $labels) {
            ksort($labels);
            $used = [];
            foreach (array_keys($labels) as $label) {
                $sub[$mainLabel][$label] = $this->uniqueGroupCode($label, 10, $used);
            }
        }

        return ['main' => $main, 'sub' => $sub];
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueGroupCode(string $label, int $length, array &$used): string {
        $base = strtoupper(strtr($label, ['ä' => 'AE', 'ö' => 'OE', 'ü' => 'UE', 'Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ß' => 'SS']));
        $base = (string) preg_replace('/[^A-Z0-9]/', '', $base);
        if ($base === '') {
            $base = 'GRP';
        }

        $code = substr($base, 0, $length);
        $counter = 1;
        while (isset($used[$code])) {
            $suffix = (string) $counter++;
            $code = substr($base, 0, max(1, $length - strlen($suffix))) . $suffix;
        }
        $used[$code] = true;

        return $code;
    }

    /**
     * @param  list<string>  $skipped
     */
    private function appendArticle(DatanormCatalog $catalog, DatanormVersion $version, DatanormPriceIndicator $priceIndicator, int &$textCounter, array &$skipped, string $number, string $name, ?string $description, ?string $unit, ?Money $price, ?string $gtin, ?string $mainGroupCode = null, ?string $subGroupCode = null, ?string $discountGroupCode = null): void {
        if (mb_strlen($number) > 15) {
            $skipped[] = $number;

            return;
        }

        $article = new DatanormArticle(
            articleNumber: $number,
            shortDescription1: mb_substr($name, 0, 40),
            shortDescription2: mb_substr($name, 40, 40),
            unit: UnitCodeMapper::toDatanorm($unit),
            priceIndicator: $priceIndicator,
            priceUnitAmount: 1,
            price: $price?->withScale(2),
            discountGroup: $discountGroupCode,
            mainProductGroup: $mainGroupCode,
            productGroup: $subGroupCode
        );
        $article->setEan($gtin);

        $description = trim((string) $description);
        if ($description !== '') {
            $textCounter++;
            $blockNumber = 'T' . str_pad((string) $textCounter, 4, '0', STR_PAD_LEFT);
            $block = new DatanormTextBlock($blockNumber, DatanormTextBlock::USAGE_LONGTEXT);
            $lineNo = 0;
            foreach ($this->wrapText($description) as $line) {
                $block->addLine(++$lineNo, $line);
            }
            $catalog->addTextBlock($block);
            $article->setLongTextNumber($blockNumber);
            $article->setTextFlag(4); // Kurztexte + Langtext (additiv)
        }

        $catalog->addArticle($article);
    }

    /**
     * Bricht die Beschreibung in 40-Zeichen-Zeilen um (DATANORM-Langtext),
     * max. 99 Zeilen je Baustein.
     *
     * @return list<string>
     */
    private function wrapText(string $text): array {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $paragraph) {
            $wrapped = wordwrap($paragraph, 40, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $lines[] = $line;
            }
        }

        return array_slice($lines, 0, 99);
    }

    /**
     * Ersteller-Stammdaten aus den E-Rechnungs-Einstellungen der Organisation
     * (gleiches Muster wie der Bestell-Export).
     *
     * @return array{name: string, street: string, country: string, zip: string, city: string}
     */
    private function creatorData(Organization $organization): array {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $einvoice = is_array($settings['einvoice'] ?? null) ? $settings['einvoice'] : [];
        $get = static fn (string $key): string => trim((string) ($einvoice[$key] ?? ''));

        return [
            'name' => $get('seller_name') !== '' ? $get('seller_name') : trim((string) $organization->name),
            'street' => $get('street'),
            'country' => strtoupper($get('country') ?: 'D'),
            'zip' => $get('zip'),
            'city' => $get('city'),
        ];
    }

    /**
     * @param  list<string>  $skipped
     */
    private function dataInfo(Organization $organization, DatanormVersion $version, string $filename, int $articles, array $skipped): string {
        $lines = [
            'DATANORM-Export ' . $organization->name,
            'Erstellt mit workDiary am ' . (new DateTimeImmutable('today'))->format('d.m.Y'),
            'Format: ' . $version->label() . ', Zeichensatz CP850',
            'Dateien: ' . $filename . ' (' . $articles . ' Artikel)',
            'Bitte ' . $filename . ' vollstaendig einlesen.',
        ];
        if ($skipped !== []) {
            $lines[] = 'Nicht exportiert (Artikelnummer laenger als 15 Zeichen): ' . implode(', ', array_slice($skipped, 0, 20));
        }

        return implode("\r\n", $lines) . "\r\n";
    }
}
