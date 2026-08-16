<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatanormImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{SupplierCatalogDiscountGroup, SupplierCatalogItem, SupplierCatalogProductGroup, SupplierCatalogSource};
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Datanorm\{DatanormArticle, DatanormCatalog, DatanormDiscount, DatanormScalePrice};
use ERechnungToolkit\Enums\{DatanormDiscountKind, DatanormPriceIndicator, DatanormProcessingFlag};
use ERechnungToolkit\Helper\DatanormPriceCalculator;
use ERechnungToolkit\Parsers\DatanormParser;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Importiert DATANORM-Dateien (Version 4 und 5) in eine Katalogquelle
 * (Feature 050/107). Parsen und Preisarithmetik übernimmt das
 * erechnung-toolkit ({@see DatanormParser}); dieser Service ist die
 * Import-Weiche für alle Dateitypen:
 *
 *  - Artikeldateien (DATANORM.nnn): Voll-Snapshot bei Neuanlage-Dateien,
 *    Delta bei Änderungsdateien (Heuristik: Änderungs-/Löschsätze vorhanden).
 *    Listenpreise (Preiskennzeichen 1) werden über die Rabattgruppe zum
 *    Netto-EK gerechnet; Umnummerierungen und Löschungen werden angewendet.
 *  - Rabattgruppen (DATANORM.RAB): Volllieferung je Quelle; anschließend
 *    werden die EK aller Listenpreis-Artikel neu berechnet.
 *  - Warengruppen (DATANORM.WRG): Klartext-Labels je Quelle.
 *  - Preisdateien (DATPREIS.nnn): Delta-Preisupdate je Artikel; der
 *    K-Kontrollsatz wird gegen die erwartete Kundennummer der Quelle geprüft.
 *
 * Preise werden als Stückpreis (÷ Preiseinheit, Skala 4) über Money gerechnet
 * (sequenzielle Rabatte, Rundung am Kettenende).
 */
class DatanormImportService {
    public function __construct(
        private readonly CatalogItemUpserter $upserter = new CatalogItemUpserter,
        private readonly DatanormParser $parser = new DatanormParser
    ) {}

    public const MODE_SNAPSHOT = 'snapshot';
    public const MODE_DELTA = 'delta';

    /** Schutzschranke Auto-Modus: ab so vielen Bestandsartikeln greift sie … */
    private const DISCONTINUE_GUARD_MIN_ITEMS = 10;

    /** … wenn der Lauf mehr als diesen Anteil des Bestands abkündigen würde. */
    private const DISCONTINUE_GUARD_RATIO = 0.5;

    /** Schlüssel für vorgemerkte zukünftige DATPREIS-Preise in extra_attributes. */
    public const PENDING_PRICE_KEY = 'datanorm_pending_price';

    /**
     * @param  string|null  $mode  {@see self::MODE_SNAPSHOT}/{@see self::MODE_DELTA}
     *                             übersteuern die Änderungsdatei-Heuristik (W4);
     *                             null = automatisch erkennen.
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     *
     * @throws RuntimeException Bei leerer Datei oder Kundennummern-Konflikt.
     */
    public function import(SupplierCatalogSource $source, string $content, ?string $mode = null): array {
        // DATANORM-Dateien sind Codepage 850; eine abweichende Quell-Einstellung
        // (z. B. UTF-8 für vorkonvertierte Feeds) wird durchgereicht.
        $encoding = strtoupper(trim((string) $source->encoding)) ?: 'CP850';
        $catalog = $this->parser->parse($content, $encoding);

        $this->verifyCustomer($source, $catalog);
        $this->storeDiscountGroups($source, $catalog);
        $this->storeProductGroups($source, $catalog);

        if ($catalog->getWarnings() !== []) {
            Log::warning('DATANORM import warnings', [
                'source_id' => $source->id,
                'count' => count($catalog->getWarnings()),
                'warnings' => array_slice($catalog->getWarnings(), 0, 20),
            ]);
        }

        if ($catalog->getArticles() !== [] || $catalog->getArticleChanges() !== []) {
            return $this->importArticles($source, $catalog, $content, $mode);
        }
        if ($catalog->getPriceChanges() !== []) {
            return $this->applyPriceChanges($source, $catalog, $content);
        }
        if ($catalog->getDiscountGroups() !== []) {
            return $this->recomputeListPrices($source, $catalog, $content);
        }
        if ($catalog->getProductGroups() !== []) {
            return ['rows' => count($catalog->getProductGroups()), 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'price_changed' => 0, 'discontinued' => 0];
        }

        throw new RuntimeException((string) __('procurement.catalog.error.no_articles'));
    }

    // ------------------------------------------------------------------
    // Artikeldateien
    // ------------------------------------------------------------------

    /**
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     */
    private function importArticles(SupplierCatalogSource $source, DatanormCatalog $catalog, string $content, ?string $mode = null): array {
        // Änderungsdateien (DATANORM.002) enthalten Änderungs-/Löschsätze oder
        // Artikel mit Verarbeitungskennzeichen A — sie dürfen den Bestand nicht
        // abkündigen. Reine Neuanlage-Dateien sind der Katalog-Snapshot.
        // Die Heuristik ist per Modus-Wahl übersteuerbar (W4).
        if ($mode === self::MODE_SNAPSHOT || $mode === self::MODE_DELTA) {
            $isDelta = $mode === self::MODE_DELTA;
        } else {
            $isDelta = $catalog->getArticleChanges() !== [];
            foreach ($catalog->getArticles() as $article) {
                $isDelta = $isDelta || $article->getProcessingFlag() === DatanormProcessingFlag::Change;
            }
            if (! $isDelta) {
                // Schutzschranke (Feature 107): eine automatisch als Vollkatalog
                // eingestufte Datei, die den Großteil des Bestands abkündigen
                // würde, ist mutmaßlich eine Änderungsdatei ohne Änderungssätze
                // (oder ein Teilkatalog) — abbrechen statt still abkündigen.
                // Der explizite Vollkatalog-Modus übersteuert die Schranke.
                $existing = $source->items()->pluck('external_no');
                if ($existing->count() >= self::DISCONTINUE_GUARD_MIN_ITEMS) {
                    $incoming = array_map(static fn (DatanormArticle $a): string => $a->getArticleNumber(), $catalog->getArticles());
                    $missing = $existing->diff($incoming)->count();
                    if ($missing / $existing->count() > self::DISCONTINUE_GUARD_RATIO) {
                        throw new RuntimeException((string) __('procurement.catalog.error.discontinue_guard', [
                            'missing' => $missing,
                            'total' => $existing->count(),
                        ]));
                    }
                }
            }
        }

        $this->applyArticleChanges($source, $catalog);

        $groups = $this->discountGroupMap($source);
        $currency = $catalog->getCurrency()->value;
        // Delta-Merge-Basis für extra_attributes (Vormerkungen nicht klobbern).
        $existingExtras = $isDelta
            ? $source->items()
                ->whereIn('external_no', array_map(static fn (DatanormArticle $a): string => $a->getArticleNumber(), $catalog->getArticles()))
                ->pluck('extra_attributes', 'external_no')
            : collect();
        $records = [];
        foreach ($catalog->getArticles() as $article) {
            $prices = $this->prices($article, $groups);
            $hasPrice = $article->getPrice() !== null;
            $record = [
                'external_no' => $article->getArticleNumber(),
                'name' => $article->getName() !== '' ? $article->getName() : $article->getArticleNumber(),
                'description' => $article->getLongText(),
                'category' => $article->getMainProductGroup(),
                'gtin' => $article->getEan(),
                'manufacturer_no' => $article->getManufacturerNumber(),
                'purchase_price' => $prices['purchase'],
                'list_price' => $prices['list'],
                'price_type' => $hasPrice ? $prices['type'] : null,
                'discount_group' => $article->getDiscountGroup(),
                'price_unit_amount' => $hasPrice ? $article->getPriceUnitAmount() : null,
                'unit' => $article->getUnit(),
                'currency' => $currency,
                'pack_size' => (string) $article->getMinPackagingAmount(),
                'base_qty' => '1',
                'tiers' => $this->tiers($article),
                'extra_attributes' => $this->extraAttributes($article),
            ];

            if ($isDelta) {
                // Elektro-Metadaten mergen statt ersetzen (Vormerkungen/Nachfolger bleiben).
                if ($record['extra_attributes'] !== null) {
                    $record['extra_attributes'] = array_merge(
                        (array) ($existingExtras->get($article->getArticleNumber()) ?? []),
                        $record['extra_attributes']
                    );
                }
                // Änderungssatz: leere Felder heißen „unverändert lassen" —
                // nur belegte Werte übergeben, damit der Upsert nichts löscht.
                // Verpackungsmenge nur anfassen, wenn sie explizit übertragen
                // wurde (Parser-Flag, toolkit ≥ 0.7.2); leere Staffeln ebenso.
                if (! $article->hasPackagingAmount()) {
                    unset($record['pack_size']);
                }
                if ($record['tiers'] === []) {
                    $record['tiers'] = null;
                }
                if ($article->getName() === '') {
                    $record['name'] = null;
                }
                $record = array_filter($record, static fn ($value): bool => $value !== null && $value !== '');
            }

            $records[] = $record;
        }

        return $this->upserter->persist($source, $records, $content, snapshot: ! $isDelta);
    }

    /** Wendet Umnummerierungen und Löschungen (B-Sätze bzw. V4 `A;L`/`A;X`) an. */
    private function applyArticleChanges(SupplierCatalogSource $source, DatanormCatalog $catalog): void {
        foreach ($catalog->getArticleChanges() as $change) {
            $item = $source->items()->where('external_no', $change->getArticleNumber())->first();
            if ($item === null) {
                continue; // Unbekannte Artikelnummer — nichts anzuwenden.
            }

            if ($change->isRenumber() && $change->getNewArticleNumber() !== null) {
                $collision = $source->items()->where('external_no', $change->getNewArticleNumber())->exists();
                if ($collision) {
                    $item->status = CatalogItemStatus::Conflict;
                } else {
                    $item->external_no = $change->getNewArticleNumber();
                }
                $item->save();

                continue;
            }

            // Löschung: verknüpfte Artikel als Konflikt, sonst Abkündigung —
            // gleiche Regel wie die Snapshot-Abkündigung des Upserters.
            $item->status = $item->article_id !== null
                ? CatalogItemStatus::Conflict
                : CatalogItemStatus::Discontinued;
            if ($change->getSuccessorArticleNumber() !== null || $change->getExpirationDate() !== null) {
                $extra = (array) ($item->extra_attributes ?? []);
                if ($change->getSuccessorArticleNumber() !== null) {
                    $extra['datanorm_successor'] = $change->getSuccessorArticleNumber();
                }
                if ($change->getExpirationDate() !== null) {
                    $extra['datanorm_expires'] = $change->getExpirationDate()->format('Y-m-d');
                }
                $item->extra_attributes = $extra;
            }
            $item->save();
        }
    }

    // ------------------------------------------------------------------
    // Preise
    // ------------------------------------------------------------------

    /**
     * Rechnet den übertragenen Artikelpreis in Stückpreise um: Netto-/
     * Streckenpreise direkt als EK, Listenpreise über die Rabattgruppe
     * (ohne bekannte Gruppe bleibt der Listenpreis als EK-Obergrenze stehen).
     *
     * @param  array<string, SupplierCatalogDiscountGroup>  $groups
     * @return array{purchase: string|null, list: string|null, type: string}
     */
    private function prices(DatanormArticle $article, array $groups): array {
        $type = $this->priceType($article->getPriceIndicator());
        if ($article->getPrice() === null) {
            return ['purchase' => null, 'list' => null, 'type' => $type];
        }

        $unitPrice = DatanormPriceCalculator::unitPrice($article->getPrice(), $article->getPriceUnitAmount());

        if ($article->getPriceIndicator() === DatanormPriceIndicator::ListPrice) {
            $net = $this->applyDiscountGroup($unitPrice, $article->getDiscountGroup(), $groups);

            return ['purchase' => $net->getAmount(), 'list' => $unitPrice->getAmount(), 'type' => $type];
        }
        if ($article->getPriceIndicator() === DatanormPriceIndicator::RecommendedPrice) {
            return ['purchase' => null, 'list' => $unitPrice->getAmount(), 'type' => $type];
        }

        return ['purchase' => $unitPrice->getAmount(), 'list' => null, 'type' => $type];
    }

    /**
     * @param  array<string, SupplierCatalogDiscountGroup>  $groups
     */
    private function applyDiscountGroup(Money $listPrice, ?string $groupCode, array $groups): Money {
        $group = $groupCode !== null ? ($groups[$groupCode] ?? null) : null;
        if ($group === null) {
            return $listPrice;
        }

        $kind = match ($group->kind) {
            SupplierCatalogDiscountGroup::KIND_FACTOR => DatanormDiscountKind::Factor,
            SupplierCatalogDiscountGroup::KIND_SURCHARGE => DatanormDiscountKind::Surcharge,
            default => DatanormDiscountKind::Discount,
        };

        return DatanormPriceCalculator::netPrice(
            $listPrice,
            [new DatanormDiscount($kind, (float) $group->value)],
            scale: 4
        );
    }

    private function priceType(DatanormPriceIndicator $indicator): string {
        return match ($indicator) {
            DatanormPriceIndicator::ListPrice => 'list',
            DatanormPriceIndicator::NetPrice => 'net',
            DatanormPriceIndicator::RoutePrice => 'route',
            DatanormPriceIndicator::RecommendedPrice => 'recommended',
            DatanormPriceIndicator::OnRequest => 'on_request',
        };
    }

    // ------------------------------------------------------------------
    // DATPREIS (P-Sätze)
    // ------------------------------------------------------------------

    /**
     * Delta-Preisupdate: P-Sätze ändern nur Preise bestehender Artikel,
     * es wird nichts abgekündigt. DATANORM-4-P-Sätze tragen keine
     * Preiseinheit — es gilt die gespeicherte des Artikels.
     *
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     */
    private function applyPriceChanges(SupplierCatalogSource $source, DatanormCatalog $catalog, string $content): array {
        $groups = $this->discountGroupMap($source);
        $items = $source->items()
            ->whereIn('external_no', array_map(static fn ($c) => $c->getArticleNumber(), $catalog->getPriceChanges()))
            ->get()
            ->keyBy('external_no');

        $records = [];
        foreach ($catalog->getPriceChanges() as $change) {
            $item = $items->get($change->getArticleNumber());
            if ($item === null) {
                continue; // Preisänderung für unbekannten Artikel — ignorieren.
            }

            $unitAmount = $change->getPriceUnitAmount() ?? (int) ($item->price_unit_amount ?? 1);
            $record = [
                'external_no' => $change->getArticleNumber(),
                'price_type' => $this->priceType($change->getPriceIndicator()),
                'price_unit_amount' => $unitAmount,
            ];

            if ($change->getPrice() !== null) {
                $unitPrice = DatanormPriceCalculator::unitPrice($change->getPrice(), max(1, $unitAmount));
                if ($change->getPriceIndicator() === DatanormPriceIndicator::ListPrice) {
                    $net = $change->getDiscounts() !== []
                        ? DatanormPriceCalculator::netPrice($unitPrice, $change->getDiscounts(), scale: 4)
                        : $this->applyDiscountGroup($unitPrice, $change->getDiscountGroup() ?? $item->discount_group, $groups);
                    $record['list_price'] = $unitPrice->getAmount();
                    $record['purchase_price'] = $net->getAmount();
                    if ($change->getDiscountGroup() !== null) {
                        $record['discount_group'] = $change->getDiscountGroup();
                    }
                } elseif ($change->getPriceIndicator() === DatanormPriceIndicator::RecommendedPrice) {
                    $record['list_price'] = $unitPrice->getAmount();
                } else {
                    $record['purchase_price'] = $unitPrice->getAmount();
                }
            }

            // Zukünftige Preisstände (P-Satz-Datum) werden nicht sofort wirksam,
            // sondern am Artikel vorgemerkt; `catalog:apply-pending-prices`
            // wendet sie am Stichtag über den Delta-Upsert an (W3-Rest).
            $validFrom = $change->getValidFrom();
            if ($validFrom !== null && $validFrom->format('Y-m-d') > now()->toDateString()) {
                $extra = (array) ($item->extra_attributes ?? []);
                $extra[self::PENDING_PRICE_KEY] = array_diff_key($record, ['external_no' => null])
                    + ['valid_from' => $validFrom->format('Y-m-d')];
                $records[] = ['external_no' => $change->getArticleNumber(), 'extra_attributes' => $extra];

                continue;
            }

            $records[] = $record;
        }

        return $this->upserter->persist($source, $records, $content, snapshot: false);
    }

    // ------------------------------------------------------------------
    // Rabatt- und Warengruppen (R/S)
    // ------------------------------------------------------------------

    private function storeDiscountGroups(SupplierCatalogSource $source, DatanormCatalog $catalog): void {
        if ($catalog->getDiscountGroups() === []) {
            return;
        }
        foreach ($catalog->getDiscountGroups() as $group) {
            SupplierCatalogDiscountGroup::query()->updateOrCreate(
                ['supplier_catalog_source_id' => $source->id, 'code' => $group->getCode()],
                [
                    'organization_id' => $source->organization_id,
                    'kind' => match ($group->getKind()) {
                        DatanormDiscountKind::Factor => SupplierCatalogDiscountGroup::KIND_FACTOR,
                        DatanormDiscountKind::Surcharge => SupplierCatalogDiscountGroup::KIND_SURCHARGE,
                        default => SupplierCatalogDiscountGroup::KIND_DISCOUNT,
                    },
                    'value' => (string) $group->getValue(),
                    'label' => $group->getLabel(),
                ]
            );
        }

        // RAB-Dateien sind Volllieferungen: nicht mehr enthaltene Gruppen entfernen.
        if ($catalog->getArticles() === [] && $catalog->getPriceChanges() === []) {
            SupplierCatalogDiscountGroup::query()
                ->where('supplier_catalog_source_id', $source->id)
                ->whereNotIn('code', array_keys($catalog->getDiscountGroups()))
                ->delete();
        }
    }

    private function storeProductGroups(SupplierCatalogSource $source, DatanormCatalog $catalog): void {
        foreach ($catalog->getProductGroups() as $group) {
            SupplierCatalogProductGroup::query()->updateOrCreate(
                [
                    'supplier_catalog_source_id' => $source->id,
                    'main_group' => $group->getMainGroup(),
                    'group' => $group->getGroup() ?? '',
                ],
                [
                    'organization_id' => $source->organization_id,
                    'label' => $group->getLabel(),
                ]
            );
        }
    }

    /**
     * Nach einer RAB-Lieferung: EK aller Listenpreis-Artikel neu berechnen
     * (Preisänderungen laufen über den Delta-Upsert inkl. Historie/Warnung).
     *
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     */
    private function recomputeListPrices(SupplierCatalogSource $source, DatanormCatalog $catalog, string $content): array {
        $groups = $this->discountGroupMap($source);
        $records = [];

        $source->items()
            ->where('price_type', 'list')
            ->whereNotNull('list_price')
            ->whereNotNull('discount_group')
            ->each(function (SupplierCatalogItem $item) use ($groups, &$records): void {
                if ($item->list_price === null || ! isset($groups[(string) $item->discount_group])) {
                    return;
                }
                $net = $this->applyDiscountGroup($item->list_price->withScale(4), $item->discount_group, $groups);
                $records[] = ['external_no' => $item->external_no, 'purchase_price' => $net->getAmount()];
            });

        $summary = $this->upserter->persist($source, $records, $content, snapshot: false);
        $summary['rows'] = count($catalog->getDiscountGroups());

        return $summary;
    }

    // ------------------------------------------------------------------
    // Helfer
    // ------------------------------------------------------------------

    private function verifyCustomer(SupplierCatalogSource $source, DatanormCatalog $catalog): void {
        $expected = trim((string) $source->expected_customer_no);
        $fileCustomer = trim((string) $catalog->getCustomer()?->getCustomerNumber());
        if ($expected !== '' && $fileCustomer !== '' && $expected !== $fileCustomer) {
            throw new RuntimeException((string) __('procurement.catalog.error.customer_mismatch', [
                'file' => $fileCustomer,
                'expected' => $expected,
            ]));
        }
    }

    /** @return array<string, SupplierCatalogDiscountGroup> code → Gruppe */
    private function discountGroupMap(SupplierCatalogSource $source): array {
        return SupplierCatalogDiscountGroup::query()
            ->where('supplier_catalog_source_id', $source->id)
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * Elektro-Metadaten (Feature 107, Branchenlücken-Runde): Rohstoffzuschläge
     * (Kupfer & Co.), Arbeitszeiten (ARBA) und V4-Kupferdaten des B-Satzes
     * landen strukturiert in `extra_attributes` — verfügbar für Kalkulation
     * und Anzeige, hash-wirksam für die Änderungserkennung.
     *
     * @return array<string, mixed>|null
     */
    private function extraAttributes(DatanormArticle $article): ?array {
        $extra = [];

        foreach ($article->getRawMaterialSurcharges() as $surcharge) {
            $entry = [
                'material' => $surcharge->getRawMaterial(),
                'method' => $surcharge->getMethod(),
                'price_unit_amount' => $surcharge->getPriceUnitAmount(),
            ];
            if ($surcharge->getMethod() === \ERechnungToolkit\Entities\Datanorm\DatanormRawMaterialSurcharge::METHOD_INTERNATIONAL) {
                $entry += array_filter([
                    'discount' => $surcharge->isDiscount() === true ? true : null,
                    'percent' => $surcharge->getPercent(),
                    'amount' => $surcharge->getAmount()?->getAmount(),
                    'from_day_price' => $surcharge->getFromDayPrice()?->getAmount(),
                    'to_day_price' => $surcharge->getToDayPrice()?->getAmount(),
                ], static fn ($v) => $v !== null);
            } else {
                $entry += array_filter([
                    'included_base' => $surcharge->getIncludedBasePrice()?->getAmount(),
                    'base_factor' => $surcharge->getBaseFactor(),
                    'weight' => $surcharge->getWeight(),
                    'weight_factor' => $surcharge->getWeightFactor(),
                ], static fn ($v) => $v !== null);
            }
            $extra['datanorm_raw_surcharges'][] = $entry;
        }

        foreach ($article->getWorkTimes() as $workTime) {
            $extra['datanorm_worktimes'][] = [
                'purpose' => $workTime->getPurpose(),
                'minutes' => $workTime->getMinutes(),
            ];
        }

        // V4-B-Satz-Kupferdaten (Kennzahl = €/100 kg im Preis, Gewicht je Merker-Einheit).
        if ((int) ($article->getCopperRawPrice() ?? '0') > 0 || (float) ($article->getCopperWeight() ?? '0') > 0) {
            $extra['datanorm_copper'] = array_filter([
                'weight_indicator' => $article->getCopperWeightIndicator(),
                'raw_price' => $article->getCopperRawPrice(),
                'weight' => $article->getCopperWeight(),
            ], static fn ($v) => $v !== null && $v !== '0');
        }

        return $extra !== [] ? $extra : null;
    }

    /**
     * Mengenstaffeln aus Staffelpreis-Z-Sätzen (Preis ersetzt den A-Satz-Preis
     * ab der Von-Menge).
     *
     * @return list<array{min_qty: string, unit_price: string}>
     */
    private function tiers(DatanormArticle $article): array {
        $tiers = [];
        foreach ($article->getScalePrices() as $scale) {
            if ($scale->getIndicator() !== DatanormScalePrice::INDICATOR_SCALE_PRICE
                || $scale->getAmount() === null
                || $scale->getFrom() === null
                || ! is_numeric($scale->getFrom())
                || ($scale->getBasis() !== null && $scale->getBasis() !== DatanormScalePrice::BASIS_QUANTITY)) {
                continue;
            }
            $tiers[] = [
                'min_qty' => $scale->getFrom(),
                'unit_price' => DatanormPriceCalculator::unitPrice($scale->getAmount(), $scale->getPriceUnitAmount())->getAmount(),
            ];
        }

        return $tiers;
    }
}
