<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogItemUpserter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{SupplierCatalogItem, SupplierCatalogItemPrice, SupplierCatalogSource};
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gemeinsame Persistenz für Katalogimporte (Feature 050) — unabhängig vom
 * Quellformat (CSV, DATANORM, …). Nimmt bereits normalisierte Datensätze
 * (Zielfeld => Wert) entgegen und garantiert:
 *  - Idempotenz über einen Rohdaten-Hash je Datensatz,
 *  - historisierte Einkaufspreise (Snapshot bei Erstimport und Preisänderung),
 *  - Abkündigung nicht mehr gelisteter Artikel (ohne Löschung),
 *  - Kalkulationswarnung bei EK-Anstieg unter die Mindestmarge (MVP-094).
 * Der interne Artikelstamm wird nie berührt.
 */
class CatalogItemUpserter {
    public const SCALE = 4;

    public function __construct(private readonly ?PriceChangeAlertService $alerts = null) {}

    /**
     * @param  list<array<string, mixed>>  $records  normalisierte Datensätze (Zielfeld => Wert)
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     */
    public function persist(SupplierCatalogSource $source, array $records, string $rawContent): array {
        $now = Carbon::now();

        return DB::transaction(function () use ($source, $records, $rawContent, $now): array {
            $summary = ['rows' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'price_changed' => 0, 'discontinued' => 0];
            $seen = [];

            foreach ($records as $values) {
                /** @var list<array{min_qty: mixed, unit_price: mixed}>|null $tiers */
                $tiers = is_array($values['tiers'] ?? null) ? $values['tiers'] : null;
                unset($values['tiers']); // kein Modellfeld; separat als Staffeln gepflegt

                $externalNo = trim((string) ($values['external_no'] ?? ''));
                if ($externalNo === '') {
                    continue; // Datensatz ohne Schlüssel wird übersprungen.
                }
                $summary['rows']++;
                $seen[] = $externalNo;

                // Staffeln gehen in den Hash ein, damit reine Staffeländerungen erkannt werden.
                $hash = CryptoHelper::hash(implode('|', array_map(fn ($v) => (string) $v, $values)) . '#' . $this->tierSignature($tiers), HashAlgorithm::SHA1);

                $item = SupplierCatalogItem::query()
                    ->where('supplier_catalog_source_id', $source->id)
                    ->where('external_no', $externalNo)
                    ->first();

                if ($item === null) {
                    $item = new SupplierCatalogItem($values);
                    $item->organization_id = $source->organization_id;
                    $item->supplier_catalog_source_id = $source->id;
                    $item->supplier_id = $source->supplier_id;
                    $item->status = CatalogItemStatus::New;
                    $item->raw_hash = $hash;
                    $item->last_seen_at = $now;
                    $item->save();
                    $this->snapshotPrice($item, $now);
                    $this->syncTiers($item, $tiers);
                    $summary['created']++;

                    continue;
                }

                $item->last_seen_at = $now;

                if ($item->raw_hash === $hash) {
                    if ($item->status === CatalogItemStatus::Discontinued) {
                        $item->status = CatalogItemStatus::New;
                    }
                    $item->save();
                    $summary['unchanged']++;

                    continue;
                }

                $oldPrice = $item->purchase_price;
                $oldGtin = $item->gtin;
                $wasLinked = $item->article_id !== null;
                $item->fill($values);
                $item->raw_hash = $hash;
                if ($item->status === CatalogItemStatus::Discontinued) {
                    $item->status = CatalogItemStatus::New;
                }
                // Konflikt: ein verknüpfter Artikel ändert seine Identität (EAN/GTIN)
                // — keine stille Mutation, sondern manuelle Prüfung (MVP-094).
                if ($wasLinked && $this->gtinChanged($oldGtin, $item->gtin)) {
                    $item->status = CatalogItemStatus::Conflict;
                }
                $item->save();

                if ($this->priceChanged($oldPrice, $item->purchase_price)) {
                    $this->snapshotPrice($item, $now);
                    $summary['price_changed']++;
                    ($this->alerts ?? app(PriceChangeAlertService::class))
                        ->evaluate($item, $oldPrice, (string) $item->purchase_price);
                }
                $this->syncTiers($item, $tiers);
                $summary['updated']++;
            }

            // Verknüpfte, nicht mehr gelistete Artikel werden NICHT still abgekündigt,
            // sondern als Konflikt markiert (lokale Referenz vorhanden).
            SupplierCatalogItem::query()
                ->where('supplier_catalog_source_id', $source->id)
                ->when($seen !== [], fn ($q) => $q->whereNotIn('external_no', $seen))
                ->whereNotNull('article_id')
                ->where('status', '!=', CatalogItemStatus::Conflict->value)
                ->update(['status' => CatalogItemStatus::Conflict->value]);

            $summary['discontinued'] = SupplierCatalogItem::query()
                ->where('supplier_catalog_source_id', $source->id)
                ->when($seen !== [], fn ($q) => $q->whereNotIn('external_no', $seen))
                ->whereNull('article_id')
                ->where('status', '!=', CatalogItemStatus::Discontinued->value)
                ->update(['status' => CatalogItemStatus::Discontinued->value]);

            $source->forceFill([
                'last_imported_at' => $now,
                'last_file_hash' => CryptoHelper::hash($rawContent, HashAlgorithm::SHA1),
            ])->save();

            return [
                'rows' => (int) $summary['rows'],
                'created' => (int) $summary['created'],
                'updated' => (int) $summary['updated'],
                'unchanged' => (int) $summary['unchanged'],
                'price_changed' => (int) $summary['price_changed'],
                'discontinued' => (int) $summary['discontinued'],
            ];
        });
    }

    /** Identitätsänderung: beide GTIN gesetzt und unterschiedlich (Neuvergabe ≠ Anreicherung). */
    private function gtinChanged(?string $old, ?string $new): bool {
        $old = trim((string) $old);
        $new = trim((string) $new);

        return $old !== '' && $new !== '' && $old !== $new;
    }

    private function priceChanged(?string $old, ?string $new): bool {
        if ($old === null || $new === null || ! is_numeric($old) || ! is_numeric($new)) {
            return $old !== $new;
        }

        return bccomp($old, $new, self::SCALE) !== 0;
    }

    /**
     * Signatur der Staffeln für die Hash-Bildung (sortiert, deterministisch).
     *
     * @param  list<array{min_qty: mixed, unit_price: mixed}>|null  $tiers
     */
    private function tierSignature(?array $tiers): string {
        if ($tiers === null || $tiers === []) {
            return '';
        }
        $parts = array_map(
            fn (array $t): string => trim((string) ($t['min_qty'] ?? '')) . ':' . trim((string) ($t['unit_price'] ?? '')),
            $tiers,
        );
        sort($parts);

        return implode(';', $parts);
    }

    /**
     * Synchronisiert die Preisstaffeln (ersetzen). null = das Format pflegt keine
     * Staffeln → bestehende bleiben unangetastet; [] = leeren.
     *
     * @param  list<array{min_qty: mixed, unit_price: mixed}>|null  $tiers
     */
    private function syncTiers(SupplierCatalogItem $item, ?array $tiers): void {
        if ($tiers === null) {
            return;
        }

        $item->priceTiers()->delete();
        foreach ($tiers as $tier) {
            $minQty = trim((string) ($tier['min_qty'] ?? ''));
            $unitPrice = trim((string) ($tier['unit_price'] ?? ''));
            if (! is_numeric($minQty) || ! is_numeric($unitPrice)) {
                continue;
            }
            $item->priceTiers()->create([
                'min_qty' => $minQty,
                'unit_price' => $unitPrice,
                'currency' => $item->currency ?? 'EUR',
            ]);
        }
    }

    private function snapshotPrice(SupplierCatalogItem $item, Carbon $at): void {
        if ($item->purchase_price === null) {
            return;
        }

        SupplierCatalogItemPrice::query()->create([
            'supplier_catalog_item_id' => $item->id,
            'purchase_price' => $item->purchase_price,
            'currency' => $item->currency ?? 'EUR',
            'captured_at' => $at,
        ]);
    }
}
