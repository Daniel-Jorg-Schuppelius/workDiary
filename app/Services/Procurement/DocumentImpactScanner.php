<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentImpactScanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Gaeb\BoqItemStatus;
use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Sales\{OrderConfirmationStatus, QuoteStatus};
use App\Models\Article\Article;
use App\Models\Contracts\DocumentLine;
use App\Models\Gaeb\{BoqItem, BoqItemMapping};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Sales\{OrderConfirmationItem, QuoteItem};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Ermittelt die offenen Vorgänge, die einen Artikel referenzieren (Feature 050,
 * MVP-094): offene Bestellungen (Bestellzeilen), offene LV-Positionen (über das
 * BoQ-Mapping), laufende Fertigungsaufträge (Materialpositionen) sowie offene
 * Angebote und Aufträge mit Artikelposition (MVP-1581). Das
 * Ergebnis wird als Label-Snapshot an der Abgleichwarnung gespeichert, damit
 * die Warnung auch nach Abschluss der Vorgänge nachvollziehbar bleibt.
 */
class DocumentImpactScanner {
    private const int LIMIT = 20;

    /**
     * @return array{purchase_orders: list<string>, boq_items: list<string>, manufacturing_orders: list<string>, quotes: list<string>, orders: list<string>}
     */
    public function scan(int $organizationId, int $articleId): array {
        $purchaseOrders = array_values(PurchaseOrder::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                PurchaseOrderStatus::Draft->value,
                PurchaseOrderStatus::Ordered->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->whereHas('lines', fn ($q) => $q->where('article_id', $articleId))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->pluck('number')
            ->map(fn ($number): string => (string) $number)
            ->all());

        $boqItemIds = BoqItemMapping::query()
            ->where('organization_id', $organizationId)
            ->where('mappable_type', (new Article())->getMorphClass())
            ->where('mappable_id', $articleId)
            ->pluck('boq_item_id');

        $boqItems = $boqItemIds->isEmpty() ? [] : array_values(BoqItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $boqItemIds)
            ->whereIn('status', [
                BoqItemStatus::Draft->value,
                BoqItemStatus::Imported->value,
                BoqItemStatus::Quoted->value,
                BoqItemStatus::Ordered->value,
                BoqItemStatus::InProgress->value,
            ])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['item_no', 'short_text'])
            ->map(fn (BoqItem $item): string => trim($item->item_no . ' ' . Str::limit((string) $item->short_text, 40)))
            ->all());

        $manufacturingOrders = array_values(ManufacturingOrder::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', [
                ManufacturingOrderStatus::Completed->value,
                ManufacturingOrderStatus::Cancelled->value,
            ])
            ->whereHas('materials', fn ($q) => $q->where('article_id', $articleId))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'number'])
            ->map(fn (ManufacturingOrder $order): string => (string) ($order->number ?? '#' . $order->id))
            ->all());

        $sales = $this->salesLines($organizationId, $articleId);

        return [
            'purchase_orders' => $purchaseOrders,
            'boq_items' => $boqItems,
            'manufacturing_orders' => $manufacturingOrders,
            'quotes' => array_values(array_unique(array_column($sales['quotes'], 'number'))),
            'orders' => array_values(array_unique(array_column($sales['orders'], 'number'))),
        ];
    }

    /**
     * Artikelpositionen offener Angebote und noch nicht stornierter Aufträge:
     * an ihrem Belegpreis misst die Margenwarnung, nicht am Stammpreis.
     *
     * @return array{quotes: list<array{line: DocumentLine, number: string}>, orders: list<array{line: DocumentLine, number: string}>}
     */
    public function salesLines(int $organizationId, int $articleId): array {
        $quoteLines = QuoteItem::query()
            ->where('organization_id', $organizationId)
            ->where('article_id', $articleId)
            ->whereHas('quote', fn (Builder $q) => $q->whereIn('status', array_map(
                static fn (QuoteStatus $status): string => $status->value,
                [QuoteStatus::Draft, ...QuoteStatus::pending()],
            )))
            ->with('quote')
            ->orderByDesc('quote_id')->orderBy('position')
            ->limit(self::LIMIT)
            ->get();

        $orderLines = OrderConfirmationItem::query()
            ->where('organization_id', $organizationId)
            ->where('article_id', $articleId)
            ->whereHas('orderConfirmation', fn (Builder $q) => $q->whereIn('status', [
                OrderConfirmationStatus::Draft->value,
                OrderConfirmationStatus::Confirmed->value,
            ]))
            ->with('orderConfirmation')
            ->orderByDesc('order_confirmation_id')->orderBy('position')
            ->limit(self::LIMIT)
            ->get();

        return [
            'quotes' => array_values($quoteLines
                ->filter(fn (QuoteItem $line): bool => $line->lineKind()->isPriced())
                ->map(fn (QuoteItem $line): array => ['line' => $line, 'number' => (string) ($line->quote->number ?? '—')])
                ->all()),
            'orders' => array_values($orderLines
                ->filter(fn (OrderConfirmationItem $line): bool => $line->lineKind()->isPriced())
                ->map(fn (OrderConfirmationItem $line): array => ['line' => $line, 'number' => (string) ($line->orderConfirmation->number ?? '—')])
                ->all()),
        ];
    }

    /** @param array{purchase_orders: list<string>, boq_items: list<string>, manufacturing_orders: list<string>, quotes: list<string>, orders: list<string>} $impacts */
    public function isEmpty(array $impacts): bool {
        return $impacts['purchase_orders'] === []
            && $impacts['boq_items'] === []
            && $impacts['manufacturing_orders'] === []
            && $impacts['quotes'] === []
            && $impacts['orders'] === [];
    }
}
