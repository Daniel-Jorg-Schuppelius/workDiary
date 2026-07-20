<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoodsReceiptService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{ArticleVariant, Organization, PurchaseOrder, PurchaseOrderLine, StockMovement, Warehouse};
use App\Services\Inventory\{InventoryLedger, InventoryValuationManager};
use App\Support\DecimalQty;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wareneingang gegen eine Bestellung (Feature 048, E4). Bucht die gelieferte
 * Menge bewertet in das Bestell-Lager und schreibt `received_qty` der Bestellzeile
 * fort. Teil- und Überlieferung sind zulässig; der Bestellstatus wird daraus neu
 * abgeleitet (teilweise/vollständig geliefert).
 */
class GoodsReceiptService {
    public const SCALE = 4;

    public function __construct(
        private readonly InventoryValuationManager $valuation,
        private readonly InventoryLedger $ledger,
        private readonly \App\Services\Inventory\LotService $lots,
        private readonly \App\Services\Inventory\SerialService $serials,
    ) {}

    public function receive(PurchaseOrderLine $line, string $qty, ?string $unitCost = null, ?int $actorUserId = null, ?string $lotNo = null, ?string $bestBefore = null, ?string $serialNo = null): StockMovement {
        $qty = DecimalQty::positive($qty);
        if (bccomp($qty, '0', self::SCALE) <= 0) {
            throw new RuntimeException('Wareneingangsmenge muss positiv sein.');
        }

        $order = $line->purchaseOrder;
        if (! $order instanceof PurchaseOrder) {
            throw new RuntimeException('Bestellzeile ohne Bestellung.');
        }
        if ($order->status === PurchaseOrderStatus::Draft) {
            throw new RuntimeException('Wareneingang erst nach Bestellung möglich.');
        }
        $warehouse = $order->warehouse;
        $variant = $this->resolveVariant($line);
        if (! $warehouse instanceof Warehouse || ! $variant instanceof ArticleVariant) {
            throw new RuntimeException('Bestellzeile ohne bestandsführende Variante/Lager.');
        }

        // Vollaudit 2026-07 (M19, Ausbaustufe E2): chargen-/serienpflichtige
        // Artikel dürfen nicht still wie gewöhnlicher Bestand gebucht werden —
        // ohne Lot-/Serienangabe wird der Wareneingang blockiert (048-Regel).
        $article = $variant->article;
        if (($article->batch_required ?? false) && trim((string) $lotNo) === '') {
            throw new RuntimeException((string) __('inventory.error.batch_required'));
        }
        if (($article->serial_required ?? false)) {
            if (trim((string) $serialNo) === '') {
                throw new RuntimeException((string) __('inventory.error.serial_required'));
            }
            if (bccomp($qty, '1', self::SCALE) !== 0) {
                throw new RuntimeException((string) __('inventory.error.serial_qty_one'));
            }
        }

        $cost = $unitCost ?? $line->unit_price ?? '0';
        $organization = Organization::query()->find($order->organization_id);

        return DB::transaction(function () use ($line, $order, $variant, $warehouse, $qty, $cost, $organization, $actorUserId, $lotNo, $bestBefore, $serialNo): StockMovement {
            // Bestellzeile gesperrt neu laden: paralleler Wareneingang gegen dieselbe
            // Zeile darf das read-modify-write von received_qty nicht überschreiben.
            $line = PurchaseOrderLine::query()->lockForUpdate()->find($line->id) ?? $line;

            if (trim((string) $lotNo) !== '') {
                // Chargen-Eingang über die FEFO-Schicht (M19): Lot anlegen/finden
                // und bewertet in die Charge buchen — statt anonymem Bestand.
                $lot = $this->lots->register($variant, (string) $lotNo, $bestBefore);
                $movement = $this->lots->receiveIntoLot($variant, $warehouse, $qty, (string) $cost, $lot, $line->currency->value, $actorUserId);
            } else {
                $movement = $organization instanceof Organization
                    ? $this->valuation->forVariant($variant, $organization)->receipt($variant, $warehouse, $qty, (string) $cost, $line->currency->value, $actorUserId, $line)
                    : $this->ledger->finishedGoodReceipt($variant, $warehouse, $qty);
            }

            if (trim((string) $serialNo) !== '') {
                // Serien-Erfassung inkl. Sperrlistenprüfung (captureForReceipt
                // war zuvor toter Code — Vollaudit 2026-07, M19).
                $this->serials->captureForReceipt($variant, (string) $serialNo, $warehouse, $actorUserId);
            }

            $line->forceFill(['received_qty' => bcadd($line->received_qty, $qty, self::SCALE)])->save();
            $this->recomputeStatus($order);

            return $movement;
        });
    }

    /** Leitet den Bestellstatus aus den Zeilen ab (teilweise/vollständig geliefert). */
    private function recomputeStatus(PurchaseOrder $order): void {
        if ($order->status->isTerminal()) {
            return;
        }

        $lines = $order->lines()->get();
        $allReceived = $lines->isNotEmpty() && $lines->every(
            fn (PurchaseOrderLine $l): bool => bccomp($l->received_qty, $l->ordered_qty, self::SCALE) >= 0
        );
        $anyReceived = $lines->contains(
            fn (PurchaseOrderLine $l): bool => bccomp($l->received_qty, '0', self::SCALE) > 0
        );

        $target = match (true) {
            $allReceived => PurchaseOrderStatus::Received,
            $anyReceived => PurchaseOrderStatus::PartiallyReceived,
            default => null,
        };

        if ($target !== null && $target !== $order->status && $order->status->canTransitionTo($target)) {
            $order->forceFill(['status' => $target])->save();
        }
    }

    private function resolveVariant(PurchaseOrderLine $line): ?ArticleVariant {
        if ($line->article_variant_id !== null) {
            return ArticleVariant::query()->find($line->article_variant_id);
        }

        return ArticleVariant::query()
            ->where('article_id', $line->article_id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
