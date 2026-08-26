<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryLedger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Models\{ArticleVariant, StockMovement, Warehouse, WarehouseBin};
use App\Support\DecimalQty;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lokale Lager-Engine (Feature 048, MVP-067): schreibt das append-only Journal
 * und leitet Salden ausschließlich daraus ab (Summe signierter Mengen je
 * Bucket). Verfügbarkeit = physisch − reserviert − gesperrt − QS. Abgänge und
 * Reservierungen prüfen die Verfügbarkeit; negativer Bestand ist nur mit
 * ausdrücklicher Freigabe möglich. Alle Beträge dezimal (bcmath).
 */
class InventoryLedger {
    public const SCALE = 4;

    /** @var array<int, \App\Enums\Inventory\InventoryMode|null> */
    private array $modeCache = [];

    /** Schreibt eine Buchung append-only; idempotent über (org, idempotency_key). */
    public function post(StockPosting $posting): StockMovement {
        $orgId = $posting->variant->organization_id;

        // Vollaudit 2026-07 (M24, MVP-066): read_only-Modus greift zentral —
        // eine Org mit gespiegeltem Fremdbestand darf lokal NICHT buchen
        // (paralleles Schreiben in zwei führende Bestände ist unzulässig).
        // Der external-Modus bleibt hier bewusst offen: dessen Spiegel-Syncs
        // buchen über genau diesen Ledger.
        $this->assertNotReadOnly($orgId);
        $this->assertPlaceUsable($posting);

        return DB::transaction(function () use ($posting, $orgId): StockMovement {
            // Idempotenzprüfung in derselben Transaktion wie der Insert; parallele Aufrufe
            // fängt der Unique-Index ab (catch unten), kein TOCTOU-Fenster.
            if ($posting->idempotencyKey !== null && $orgId !== null) {
                $existing = $this->findByIdempotencyKey($orgId, $posting->idempotencyKey, lock: true);
                if ($existing !== null) {
                    return $existing; // Doppelbuchung verhindert
                }
            }

            try {
                $movement = StockMovement::query()->create([
                    'organization_id' => $orgId,
                    'article_variant_id' => $posting->variant->id,
                    'warehouse_id' => $posting->warehouse->id,
                    'bin_id' => $posting->bin?->id,
                    'stock_lot_id' => $posting->stockLotId,
                    'stock_serial_id' => $posting->stockSerialId,
                    'stock_state' => $posting->state->value,
                    'ownership_type' => $posting->ownership->value,
                    'owner_ref' => $posting->ownerRef,
                    'movement_type' => $posting->type->value,
                    'qty_base' => $posting->signedQty,
                    'original_qty' => $posting->originalQty,
                    'original_unit' => $posting->originalUnit,
                    'occurred_at' => Carbon::now(),
                    'actor_user_id' => $posting->actorUserId,
                    'source_type' => $posting->source?->getMorphClass(),
                    'source_id' => $posting->source?->getKey(),
                    'idempotency_key' => $posting->idempotencyKey,
                    'cost_unit' => $posting->costUnit,
                    'cost_total' => $posting->costTotal,
                    'currency' => $posting->currency,
                ]);

                // Zentraler Spiegel (Feature 078): jedes physische Delta wandert bei externer
                // Bestandsführung in die Outbox. Lazy (Provider-Resolver-Zyklus); Replays spiegeln nicht erneut.
                app(ExternalStockMirror::class)->mirrorMovement($movement);

                return $movement;
            } catch (QueryException $e) {
                // Rennen um den Unique-Index verloren: bestehende Buchung idempotent zurückgeben statt 500.
                if ($posting->idempotencyKey !== null && $orgId !== null) {
                    $existing = $this->findByIdempotencyKey($orgId, $posting->idempotencyKey, lock: false);
                    if ($existing !== null) {
                        return $existing;
                    }
                }
                throw $e;
            }
        });
    }

    /**
     * Zentraler Modus-Guard (Vollaudit 2026-07, M24): blockt lokale Buchungen
     * im read_only-Modus. Ergebnis je Org auf der Instanz gecacht (nicht
     * static — Org-IDs wiederholen sich zwischen Requests/Tests) — post()
     * läuft in Massenpfaden (Import, Fertigung).
     */
    private function assertNotReadOnly(?int $orgId): void {
        if ($orgId === null) {
            return;
        }

        if (! array_key_exists($orgId, $this->modeCache)) {
            $organization = \App\Models\Organization::query()->find($orgId);
            $this->modeCache[$orgId] = $organization !== null
                ? app(InventoryProviderResolver::class)->modeFor($organization)
                : null;
        }

        if ($this->modeCache[$orgId] === \App\Enums\Inventory\InventoryMode::ReadOnly) {
            throw new RuntimeException((string) __('Bestandsführung ist read-only — lokale Buchungen sind gesperrt (führendes System ist extern).'));
        }
    }

    /**
     * Lagerort-/Lagerplatz-Guard (MVP-706): ein gesperrter oder inaktiver Ort
     * nimmt keine Zu-/Abgänge und Reservierungen an; Korrekturen (Inventur)
     * und Reservierungsfreigaben bleiben möglich, damit ein gesperrter Platz
     * bereinigt werden kann. Ein Platz muss zum gebuchten Lagerort gehören —
     * sonst wäre der Bucket (Lager, Platz) widersprüchlich.
     */
    private function assertPlaceUsable(StockPosting $posting): void {
        $bin = $posting->bin;
        if ($bin !== null && $bin->warehouse_id !== $posting->warehouse->id) {
            throw new RuntimeException((string) __('inventory.error.bin_foreign'));
        }

        if (in_array($posting->type, [StockMovementType::Correction, StockMovementType::ReleaseReservation], true)) {
            return;
        }

        $warehouse = $posting->warehouse;
        if ($warehouse->blocked || ! $warehouse->active) {
            throw new RuntimeException((string) __('inventory.error.warehouse_unusable', ['name' => $warehouse->name]));
        }
        if ($bin !== null && ! $bin->isUsable()) {
            throw new RuntimeException((string) __('inventory.error.bin_unusable', ['code' => $bin->code]));
        }
    }

    private function findByIdempotencyKey(int $orgId, string $key, bool $lock): ?StockMovement {
        $query = StockMovement::query()
            ->where('organization_id', $orgId)
            ->where('idempotency_key', $key);
        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var StockMovement|null */
        return $query->first();
    }

    /**
     * Saldo eines Bestandszustands (optional je Eigentumsart) in Basiseinheit.
     *
     * @return numeric-string
     */
    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state, ?OwnershipType $ownership = null, ?WarehouseBin $bin = null): string {
        $query = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_state', $state->value);
        if ($ownership !== null) {
            $query->where('ownership_type', $ownership->value);
        }
        if ($bin !== null) {
            $query->where('bin_id', $bin->id);
        }

        $sum = '0';
        foreach ($query->pluck('qty_base') as $value) {
            $sum = bcadd($sum, NumberHelper::normalizeDecimalString((string) $value), self::SCALE);
        }

        return bcadd($sum, '0', self::SCALE);
    }

    /**
     * Salden ALLER Varianten eines Lagers je Bestandszustand in einem Durchlauf
     * (Vollscan 2026-08-23, A4): die Lagerübersicht rief sonst je Variante
     * viermal balance() auf — jeder Aufruf ein Volldurchlauf des Buckets.
     * Eine Query über die Skalarspalten, bc-Summe in PHP (exakt auf MariaDB
     * wie SQLite — SUM() würde in SQLite als Float rechnen).
     *
     * @return array<int, array<string, numeric-string>> variant_id → [state => Saldo]
     */
    public function balancesByVariant(Warehouse $warehouse): array {
        $sums = [];
        $rows = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->toBase()
            ->get(['article_variant_id', 'stock_state', 'qty_base']);
        foreach ($rows as $row) {
            $variantId = (int) $row->article_variant_id;
            $state = (string) $row->stock_state;
            $sums[$variantId][$state] = bcadd(
                $sums[$variantId][$state] ?? '0',
                NumberHelper::normalizeDecimalString((string) $row->qty_base),
                self::SCALE,
            );
        }

        return $sums;
    }

    /**
     * Saldo je Lagerplatz (MVP-706) für einen Bestandszustand; Schlüssel 0 =
     * Bewegungen ohne Platz. Eine Query, bc-Summe in PHP (wie balancesByVariant).
     *
     * @return array<int, numeric-string> bin_id|0 → Saldo
     */
    public function balancesByBin(ArticleVariant $variant, Warehouse $warehouse, StockState $state = StockState::Physical): array {
        $sums = [];
        $rows = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_state', $state->value)
            ->toBase()
            ->get(['bin_id', 'qty_base']);
        foreach ($rows as $row) {
            $binId = (int) ($row->bin_id ?? 0);
            $sums[$binId] = bcadd($sums[$binId] ?? '0', NumberHelper::normalizeDecimalString((string) $row->qty_base), self::SCALE);
        }
        ksort($sums);

        return $sums;
    }

    /**
     * Physische Salden ALLER Varianten eines Lagers je Lagerplatz in einem
     * Durchlauf (Bestandsübersicht, MVP-706). Schlüssel 0 = ohne Platz.
     *
     * @return array<int, array<int, numeric-string>> variant_id → [bin_id|0 => Saldo]
     */
    public function binBalancesByVariant(Warehouse $warehouse): array {
        $sums = [];
        $rows = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_state', StockState::Physical->value)
            ->toBase()
            ->get(['article_variant_id', 'bin_id', 'qty_base']);
        foreach ($rows as $row) {
            $variantId = (int) $row->article_variant_id;
            $binId = (int) ($row->bin_id ?? 0);
            $sums[$variantId][$binId] = bcadd(
                $sums[$variantId][$binId] ?? '0',
                NumberHelper::normalizeDecimalString((string) $row->qty_base),
                self::SCALE,
            );
        }

        return $sums;
    }

    /**
     * Verfügbar auf einem Lagerplatz = physisch − reserviert − gesperrt − QS,
     * jeweils nur Bewegungen dieses Platzes (MVP-706).
     *
     * @return numeric-string
     */
    public function availableInBin(ArticleVariant $variant, Warehouse $warehouse, WarehouseBin $bin): string {
        $result = $this->balance($variant, $warehouse, StockState::Physical, bin: $bin);
        foreach ([StockState::Reserved, StockState::Blocked, StockState::Quality] as $state) {
            $result = bcsub($result, $this->balance($variant, $warehouse, $state, bin: $bin), self::SCALE);
        }

        return $result;
    }

    /**
     * Verfügbar aus einem Saldensatz von {@see balancesByVariant()}.
     *
     * @param  array<string, numeric-string>  $balances  state → Saldo
     * @return numeric-string
     */
    public static function availableFromBalances(array $balances): string {
        $result = bcadd($balances[StockState::Physical->value] ?? '0', '0', self::SCALE);
        foreach ([StockState::Reserved, StockState::Blocked, StockState::Quality] as $state) {
            $result = bcsub($result, $balances[$state->value] ?? '0', self::SCALE);
        }

        return $result;
    }

    /**
     * Verfügbar = physisch − reserviert − gesperrt − QS (über alle Eigentumsarten).
     *
     * @return numeric-string
     */
    public function available(ArticleVariant $variant, Warehouse $warehouse): string {
        $physical = $this->balance($variant, $warehouse, StockState::Physical);
        $result = $physical;
        foreach ([StockState::Reserved, StockState::Blocked, StockState::Quality] as $state) {
            $result = bcsub($result, $this->balance($variant, $warehouse, $state), self::SCALE);
        }

        return $result;
    }

    /**
     * Wie {@see available()}, sperrt aber die zugrunde liegenden Bewegungszeilen
     * des Buckets (`SELECT … FOR UPDATE`). Nur innerhalb einer Transaktion sinnvoll:
     * konkurrierende Abgänge/Reservierungen serialisieren so über denselben Bestand
     * und können nicht beide gegen denselben Saldo buchen (kein Überverkauf).
     *
     * @return numeric-string
     */
    public function availableForUpdate(ArticleVariant $variant, Warehouse $warehouse, ?WarehouseBin $bin = null): string {
        $rows = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->when($bin !== null, fn ($q) => $q->where('bin_id', $bin?->id))
            ->whereIn('stock_state', [
                StockState::Physical->value,
                StockState::Reserved->value,
                StockState::Blocked->value,
                StockState::Quality->value,
            ])
            ->lockForUpdate()
            ->get(['stock_state', 'qty_base']);

        $result = '0';
        foreach ($rows as $row) {
            $qty = NumberHelper::normalizeDecimalString((string) $row->qty_base);
            $result = $row->stock_state === StockState::Physical
                ? bcadd($result, $qty, self::SCALE)
                : bcsub($result, $qty, self::SCALE);
        }

        return bcadd($result, '0', self::SCALE);
    }

    // ── Semantische Buchungen ───────────────────────────────────────────
    // `$bin` (MVP-706) ist optionales letztes Argument — bestehende Aufrufer bleiben unverändert.

    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null, ?WarehouseBin $bin = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, DecimalQty::positive($qty), StockMovementType::Receipt, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId, bin: $bin));
    }

    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, bool $allowNegative = false, ?string $idempotencyKey = null, ?int $actorUserId = null, ?WarehouseBin $bin = null): StockMovement {
        // Prüfung + Buchung in einer Transaktion: availableForUpdate() sperrt den Bestand gegen parallele Abgänge.
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $allowNegative, $idempotencyKey, $actorUserId, $bin): StockMovement {
            $this->guardSufficient($variant, $warehouse, $qty, $allowNegative, $bin);

            return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, DecimalQty::negative($qty), StockMovementType::Issue, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId, bin: $bin));
        });
    }

    public function reserve(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null, ?WarehouseBin $bin = null): StockMovement {
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $idempotencyKey, $actorUserId, $bin): StockMovement {
            $this->guardSufficient($variant, $warehouse, $qty, false, $bin);

            return $this->post(new StockPosting($variant, $warehouse, StockState::Reserved, DecimalQty::positive($qty), StockMovementType::Reserve, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId, bin: $bin));
        });
    }

    public function releaseReservation(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null, ?WarehouseBin $bin = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, StockState::Reserved, DecimalQty::negative($qty), StockMovementType::ReleaseReservation, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId, bin: $bin));
    }

    public function finishedGoodReceipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null, ?WarehouseBin $bin = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, DecimalQty::positive($qty), StockMovementType::FinishedGoodReceipt, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId, bin: $bin));
    }

    /** Inventurdifferenz/Gegenbuchung: signierte Menge auf einen Zustand. */
    public function correction(ArticleVariant $variant, Warehouse $warehouse, StockState $state, string $signedQty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null, ?WarehouseBin $bin = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, $state, NumberHelper::normalizeDecimalString($signedQty), StockMovementType::Correction, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId, bin: $bin));
    }

    private function guardSufficient(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative, ?WarehouseBin $bin = null): void {
        if ($allowNegative) {
            return;
        }
        // Mit Platz zählt nur der Bestand dieses Platzes (kein Abgang „aus dem Nachbarregal").
        if (bccomp($this->availableForUpdate($variant, $warehouse, $bin), DecimalQty::positive($qty), self::SCALE) < 0) {
            throw new RuntimeException('Nicht genügend verfügbarer Bestand (negativer Bestand nicht freigegeben).');
        }
    }

}
