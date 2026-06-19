<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Numbering\NumberScope;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, ArticleVariant, Organization, PurchaseOrder, PurchaseOrderLine, Supplier, Warehouse};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Bestellabwicklung (Feature 048, E4): Entwurf anlegen, Zeilen pflegen, beim
 * Lieferanten bestellen und den Status über die Statusmaschine führen. Der
 * Wareneingang gegen die Bestellung erfolgt im {@see GoodsReceiptService}.
 */
class PurchaseOrderService {
    public const SCALE = 4;

    public function __construct(private readonly NumberSequenceService $numbers) {}

    /** @param array<string, mixed> $options */
    public function createDraft(Organization $organization, Supplier $supplier, Warehouse $warehouse, array $options = []): PurchaseOrder {
        return PurchaseOrder::query()->create([
            'organization_id' => $organization->id,
            'number' => $this->numbers->next($organization, NumberScope::PurchaseOrder),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Draft->value,
            'currency' => $options['currency'] ?? 'EUR',
            'expected_at' => $options['expected_at'] ?? null,
            'note' => $options['note'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $options */
    public function addLine(PurchaseOrder $order, Article $article, string $qty, array $options = []): PurchaseOrderLine {
        $variant = $options['variant'] ?? null;

        return $order->lines()->create([
            'organization_id' => $order->organization_id,
            'article_id' => $article->id,
            'article_variant_id' => $variant instanceof ArticleVariant ? $variant->id : null,
            'supplier_sku' => $options['supplier_sku'] ?? null,
            'description' => $options['description'] ?? $article->name,
            'ordered_qty' => $this->positive($qty),
            'received_qty' => '0',
            'unit' => $options['unit'] ?? $article->base_unit,
            'unit_price' => $options['unit_price'] ?? null,
            'currency' => $options['currency'] ?? $order->currency,
        ]);
    }

    /** Beim Lieferanten bestellen (Draft → Ordered). */
    public function submit(PurchaseOrder $order): PurchaseOrder {
        $this->transition($order, PurchaseOrderStatus::Ordered);
        $order->forceFill(['ordered_at' => Carbon::now()])->save();

        return $order;
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder {
        return $this->transition($order, PurchaseOrderStatus::Cancelled);
    }

    public function transition(PurchaseOrder $order, PurchaseOrderStatus $target): PurchaseOrder {
        if ($order->status === $target) {
            return $order;
        }
        if (! $order->status->canTransitionTo($target)) {
            throw new RuntimeException('Unzulässiger Statuswechsel: ' . $order->status->value . ' → ' . $target->value);
        }
        $order->forceFill(['status' => $target])->save();

        return $order;
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
