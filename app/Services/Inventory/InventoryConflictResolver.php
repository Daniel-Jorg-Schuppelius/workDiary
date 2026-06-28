<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\{ArticleVariant, PendingExternalConflict, StockMovement, Warehouse};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Löst kompensationspflichtige Inventory-Outbox-Konflikte auf (Feature 048,
 * MVP-072). Eine lokal gebuchte Bewegung, deren externe Spiegelung endgültig
 * fehlgeschlagen ist, wird hier fachlich ausgeglichen — entweder durch
 * bewusstes Beibehalten des lokalen Standes oder durch eine **Gegenbuchung**
 * (niemals per DB-Rollback). Der Konflikt wird damit geschlossen.
 */
class InventoryConflictResolver {
    public const SCALE = 4;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Behält den lokalen Bestand bei (externe Differenz akzeptiert) und schließt
     * den Konflikt ohne Gegenbuchung.
     */
    public function keepLocal(PendingExternalConflict $conflict, ?int $userId = null): void {
        $this->guard($conflict);

        $conflict->forceFill([
            'status' => PendingExternalConflict::STATUS_RESOLVED_LOCAL,
            'resolved_by' => $userId,
            'resolved_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Gleicht die lokal gebuchte Bewegung durch eine betragsgleiche Gegenbuchung
     * im selben Bestandszustand aus und schließt den Konflikt.
     *
     * @throws RuntimeException Wenn die zugrunde liegende Bewegung fehlt.
     */
    public function compensate(PendingExternalConflict $conflict, ?int $userId = null): StockMovement {
        $this->guard($conflict);

        return DB::transaction(function () use ($conflict, $userId): StockMovement {
            $movement = StockMovement::query()->withoutGlobalScopes()->find($conflict->referenceable_id);
            if (! $movement instanceof StockMovement) {
                throw new RuntimeException('Zur Kompensation fehlt die lokale Bewegung.');
            }

            $variant = ArticleVariant::query()->withoutGlobalScopes()->find($movement->article_variant_id);
            $warehouse = Warehouse::query()->withoutGlobalScopes()->find($movement->warehouse_id);
            if (! $variant instanceof ArticleVariant || ! $warehouse instanceof Warehouse) {
                throw new RuntimeException('Zur Kompensation fehlt Variante oder Lagerort.');
            }

            $qtyBase = (string) $movement->qty_base;
            if (! is_numeric($qtyBase)) {
                throw new RuntimeException('Ungültiger Bewegungswert für die Kompensation.');
            }

            // Gegenbuchung: negierter Delta-Wert im selben Zustand hebt die
            // ursprüngliche Wirkung auf. Idempotenz über einen abgeleiteten Key.
            $reversal = $this->ledger->correction(
                $variant,
                $warehouse,
                $movement->stock_state,
                bcmul($qtyBase, '-1', self::SCALE),
                $movement->ownership_type,
                idempotencyKey: 'compensate:' . $movement->id,
                actorUserId: $userId,
            );

            $conflict->forceFill([
                'status' => PendingExternalConflict::STATUS_COMPENSATED,
                'resolved_by' => $userId,
                'resolved_at' => Carbon::now(),
            ])->save();

            return $reversal;
        });
    }

    private function guard(PendingExternalConflict $conflict): void {
        if ($conflict->conflict_type !== 'inventory_outbox') {
            throw new RuntimeException('Kein Inventory-Outbox-Konflikt.');
        }
        if (! $conflict->isOpen()) {
            throw new RuntimeException('Konflikt ist bereits aufgelöst.');
        }
    }
}
