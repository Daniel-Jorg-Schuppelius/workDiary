<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRmaService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims;

use App\Enums\Claims\{ClaimRmaDisposition, ClaimRmaStatus};
use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Enums\Numbering\NumberScope;
use App\Models\Claims\{ClaimCase, ClaimInspection, ClaimRmaReturn};
use App\Models\User;
use App\Services\Inventory\{InventoryLedger, SerialService, StockPosting};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Facades\DB;

/**
 * RMA-/Rückläuferprozess (Feature 072, MVP-250): Rücksendenummer,
 * Wareneingang in Quarantäne (Bestandszustand quality/blocked/damaged),
 * Prüfung inkl. Seriennummernabgleich, Verwendungsentscheidung mit
 * idempotenten Lagerbuchungen. Bestandshoheit bleibt beim Ledger —
 * dieser Service bucht nur nachvollziehbare Bewegungen.
 */
class ClaimRmaService {
    /** Zulässige Quarantäne-Bestandszustände beim Wareneingang. */
    public const QUARANTINE_STATES = ['quality', 'blocked', 'damaged'];

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly InventoryLedger $ledger,
        private readonly SerialService $serials,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function announce(ClaimCase $case, array $attributes): ClaimRmaReturn {
        return DB::transaction(fn(): ClaimRmaReturn => $case->rmaReturns()->create(array_merge($attributes, [
            'organization_id' => $case->organization_id,
            'rma_number' => $this->numbers->next($case->organization_id, NumberScope::Rma),
            'status' => ClaimRmaStatus::Announced->value,
        ])));
    }

    /**
     * Wareneingang (MVP-250): bucht bei Artikel-/Mengenbezug idempotent in
     * den gewählten Quarantäne-Zustand; Seriennummern werden als Rückläufer
     * markiert (Statuswechsel, Bestandswirkung nur über den Ledger).
     *
     * @param array<string, mixed> $attributes warehouse_id, qty?, stock_state?, condition_note?
     */
    public function receive(ClaimRmaReturn $rma, User $actor, array $attributes): ClaimRmaReturn {
        $state = (string) ($attributes['stock_state'] ?? 'quality');
        if (! in_array($state, self::QUARANTINE_STATES, true)) {
            throw new \InvalidArgumentException('Unzulässiger Quarantäne-Zustand: ' . $state);
        }

        return DB::transaction(function () use ($rma, $actor, $attributes, $state): ClaimRmaReturn {
            $rma->fill([
                'warehouse_id' => $attributes['warehouse_id'] ?? $rma->warehouse_id,
                'qty' => $attributes['qty'] ?? $rma->qty,
                'stock_state' => $state,
                'condition_note' => $attributes['condition_note'] ?? $rma->condition_note,
            ]);
            $rma->forceFill([
                'status' => ClaimRmaStatus::Received->value,
                'received_at' => now(),
                'received_by' => $actor->id,
            ])->save();

            $rma->refresh();
            $qtyRaw = (string) $rma->qty;
            $qty = is_numeric($qtyRaw) ? bcadd($qtyRaw, '0', 4) : '0.0000';
            if ($rma->articleVariant !== null && $rma->warehouse !== null && (float) $qty > 0) {
                $this->ledger->post(new StockPosting(
                    $rma->articleVariant,
                    $rma->warehouse,
                    StockState::from($state),
                    $qty,
                    StockMovementType::Return,
                    OwnershipType::Own,
                    idempotencyKey: 'claim-rma:' . $rma->id . ':receive',
                    actorUserId: $actor->id,
                    source: $rma,
                    stockLotId: $rma->stock_lot_id,
                    stockSerialId: $rma->stock_serial_id,
                ));
            }
            if ($rma->stockSerial !== null) {
                $this->serials->returnSerial($rma->stockSerial, $rma->warehouse);
            }

            return $rma;
        });
    }

    /**
     * Prüfung (MVP-250): Ergebnis + Befund; bei Seriennummernbezug wird
     * dokumentiert, ob die Nummer je an den Fall-Kunden geliefert wurde.
     *
     * @param array<string, mixed> $attributes result, findings?
     */
    public function inspect(ClaimRmaReturn $rma, User $inspector, array $attributes): ClaimInspection {
        $result = (string) ($attributes['result'] ?? '');
        if (! in_array($result, ClaimInspection::RESULTS, true)) {
            throw new \InvalidArgumentException('Unbekanntes Prüfergebnis: ' . $result);
        }

        return DB::transaction(function () use ($rma, $inspector, $attributes, $result): ClaimInspection {
            $serialChecked = false;
            $serialResult = null;
            $case = $rma->claimCase;
            $serial = trim((string) ($rma->serial_no ?? $case->serial_no ?? ''));
            if ($serial !== '' && $case?->customer !== null) {
                $serialChecked = true;
                $serialResult = $this->serials->wasShippedTo((int) $rma->organization_id, $serial, $case->customer)
                    ? 'shipped_to_customer'
                    : 'not_shipped_to_customer';
            }

            $inspection = $rma->inspections()->create([
                'organization_id' => $rma->organization_id,
                'result' => $result,
                'findings' => $attributes['findings'] ?? null,
                'serial_checked' => $serialChecked,
                'serial_check_result' => $serialResult,
                'inspected_by' => $inspector->id,
                'inspected_at' => now(),
            ]);

            if ($rma->status === ClaimRmaStatus::Received) {
                $rma->forceFill(['status' => ClaimRmaStatus::Inspecting->value])->save();
            }

            return $inspection;
        });
    }

    /**
     * Verwendungsentscheidung (MVP-250): Wiedereinlagerung, Reparatur,
     * Rücksendung an den Lieferanten, Verschrottung oder Entsorgung —
     * jede bestandswirksame Folge als idempotente Ledger-Buchung.
     */
    public function decideDisposition(ClaimRmaReturn $rma, User $actor, ClaimRmaDisposition $disposition, ?string $note = null): ClaimRmaReturn {
        if ($rma->status === ClaimRmaStatus::Announced) {
            throw new \RuntimeException((string) __('Ohne Wareneingang gibt es keine Verwendungsentscheidung.'));
        }

        return DB::transaction(function () use ($rma, $actor, $disposition, $note): ClaimRmaReturn {
            $variant = $rma->articleVariant;
            $warehouse = $rma->warehouse;
            $qtyRaw = (string) $rma->qty;
            $qtyIn = is_numeric($qtyRaw) ? bcadd($qtyRaw, '0', 4) : '0.0000';
            $qtyOut = bcmul($qtyIn, '-1', 4);
            $state = $rma->stock_state !== null ? StockState::from($rma->stock_state) : StockState::Quality;
            $hasStock = $variant !== null && $warehouse !== null && (float) $qtyIn > 0;

            switch ($disposition) {
                case ClaimRmaDisposition::Restock:
                    if ($hasStock) {
                        // Quarantäne → frei verfügbar (zwei Korrekturzeilen).
                        $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Correction, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':restock-out', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                        $this->ledger->post(new StockPosting($variant, $warehouse, StockState::Physical, $qtyIn, StockMovementType::Correction, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':restock-in', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                    }
                    if ($rma->stockSerial !== null) {
                        $this->serials->unblock($rma->stockSerial, $warehouse);
                    }
                    break;
                case ClaimRmaDisposition::Scrap:
                case ClaimRmaDisposition::Dispose:
                    if ($hasStock) {
                        $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Scrap, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':scrap', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                    }
                    if ($rma->stockSerial !== null) {
                        $this->serials->scrap($rma->stockSerial);
                    }
                    break;
                case ClaimRmaDisposition::ReturnToSupplier:
                    if ($hasStock) {
                        $this->ledger->post(new StockPosting($variant, $warehouse, $state, $qtyOut, StockMovementType::Issue, OwnershipType::Own, idempotencyKey: 'claim-rma:' . $rma->id . ':rts', actorUserId: $actor->id, source: $rma, stockLotId: $rma->stock_lot_id, stockSerialId: $rma->stock_serial_id));
                    }
                    break;
                case ClaimRmaDisposition::Repair:
                    // bleibt in Quarantäne; Maßnahme (MVP-251) steuert weiter
                    break;
            }

            $rma->forceFill([
                'status' => ClaimRmaStatus::Completed->value,
                'disposition' => $disposition->value,
                'disposition_note' => $note,
                'disposed_at' => now(),
                'disposed_by' => $actor->id,
            ])->save();

            return $rma;
        });
    }
}
