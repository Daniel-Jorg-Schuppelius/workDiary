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
use CommonToolkit\Enums\CurrencyCode;
use ERechnungToolkit\Entities\Bmecat\{BmecatArticle, BmecatCatalog, BmecatPrice};
use ERechnungToolkit\Parsers\BmecatParser;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Importiert einen BMEcat-Produktkatalog (XML, 1.2 oder 2005) in die
 * Katalogartikel einer Quelle (Feature 050, „Später": strukturierte
 * Katalogformate). Das Parsen übernimmt seit C7 (Vollscan 2026-08-23) der
 * {@see BmecatParser} des erechnung-toolkits (v0.12); dieser Service mappt die
 * Toolkit-Entities auf die normalisierten Datensätze des
 * {@see CatalogItemUpserter}. Behandelt die Datei als vollständigen
 * Katalog-Snapshot (nicht enthaltene Artikel werden abgekündigt).
 */
class BMEcatImportService {
    public function __construct(
        private readonly CatalogItemUpserter $upserter = new CatalogItemUpserter,
        private readonly BmecatParser $parser = new BmecatParser
    ) {}

    /**
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     *
     * @throws RuntimeException Bei ungültigem XML oder ohne Artikelelemente.
     */
    public function import(SupplierCatalogSource $source, string $content): array {
        try {
            $catalog = $this->parser->parse($content);
        } catch (RuntimeException $e) {
            // Toolkit-Fehler (kaputtes XML, fremdes Wurzelelement, unbekannte
            // Version) → eine übersetzte Nutzer-Meldung.
            throw new RuntimeException((string) __('procurement.catalog.error.invalid_xml'), previous: $e);
        }

        // Nicht-fatale Parser-Warnungen loggen (Muster DatanormImportService).
        if ($catalog->getWarnings() !== []) {
            Log::warning('BMEcat import warnings', [
                'source_id' => $source->id,
                'count' => count($catalog->getWarnings()),
                'warnings' => array_slice($catalog->getWarnings(), 0, 20),
            ]);
        }

        $records = $this->records($catalog);
        if ($records === []) {
            throw new RuntimeException((string) __('procurement.catalog.error.no_articles'));
        }

        return $this->upserter->persist($source, $records, $content);
    }

    /**
     * Mappt die Toolkit-Entities auf die Upsert-Datensätze. `pack_size`/
     * `base_qty` '1' sind BMEcat-seitig nicht belegt und bleiben App-Regel.
     *
     * @return list<array<string, mixed>>
     */
    private function records(BmecatCatalog $catalog): array {
        $records = [];
        foreach ($catalog->getArticles() as $article) {
            $price = $article->getPrice();
            $records[] = [
                'external_no' => $article->getArticleNumber(),
                'name' => $article->getName(),
                'description' => $article->getDescriptionLong(),
                'gtin' => $article->getEan(),
                'manufacturer_no' => $article->getManufacturerArticleNumber(),
                'manufacturer' => $article->getManufacturerName(),
                'classification_system' => $article->getClassificationSystem(),
                'classification_code' => $article->getClassificationGroupId(),
                'image_url' => $article->getImageUrl(),
                'datasheet_url' => $article->getDatasheetUrl(),
                'purchase_price' => $price?->getAmount()?->getAmount(),
                'currency' => ($price?->getCurrency() ?? $catalog->getCurrency() ?? CurrencyCode::Euro)->value,
                'pack_size' => '1',
                'base_qty' => '1',
                'tiers' => $this->tiers($article),
            ];
        }

        return $records;
    }

    /**
     * Mengenstaffeln (LOWER_BOUND > 1); der Basispreis steht am Artikel.
     * `min_qty` mit Skala 4, damit die Staffel-Signatur des Upserters bei
     * unveränderten Wiederholungsimporten stabil bleibt.
     *
     * @return list<array{min_qty: string, unit_price: string}>
     */
    private function tiers(BmecatArticle $article): array {
        return array_map(static fn (BmecatPrice $price): array => [
            'min_qty' => number_format($price->getLowerBound(), 4, '.', ''),
            'unit_price' => (string) $price->getAmount()?->getAmount(),
        ], $article->getScalePrices());
    }
}
