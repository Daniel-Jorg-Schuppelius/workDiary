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
    ) {}

    public function receive(PurchaseOrderLine $line, string $qty, ?string $unitCost = null, ?int $actorUserId = null): StockMovement {
        $qty = $this->positive($qty);
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

        $cost = $unitCost ?? $line->unit_price ?? '0';
        $organization = Organization::query()->find($order->organization_id);

        return DB::transaction(function () use ($line, $order, $variant, $warehouse, $qty, $cost, $organization, $actorUserId): StockMovement {
            $movement = $organization instanceof Organization
                ? $this->valuation->forVariant($variant, $organization)->receipt($variant, $warehouse, $qty, (string) $cost, (string) $line->currency, $actorUserId, $line)
                : $this->ledger->finishedGoodReceipt($variant, $warehouse, $qty);

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

    /** @return numeric-string */
    private function positive(string $value): string {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : $value;
    }
}
