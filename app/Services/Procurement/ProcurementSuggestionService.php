<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcurementSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Manufacturing\ProcurementStatus;
use App\Models\{Article, ArticleSupply, ArticleVariant, Organization, ProcurementRequest, PurchaseOrder, Supplier, Warehouse};
use App\Services\Inventory\StockLevelService;

/**
 * Automatische Bestellvorschläge (Feature 048, E4): bündelt Meldebestand-
 * Unterschreitungen ({@see StockLevelService}) und offene Beschaffungs-
 * anforderungen je Artikel, wählt die bevorzugte Bezugsquelle und rundet die
 * Menge auf Mindestbestellmenge (MOQ) und Verpackungseinheit. Aus den Vorschlägen
 * lassen sich je Lieferant Entwurfsbestellungen erzeugen.
 */
class ProcurementSuggestionService {
    public const SCALE = 4;

    public function __construct(
        private readonly StockLevelService $levels,
        private readonly PurchaseOrderService $orders,
    ) {}

    /**
     * @return list<array{article: Article, supply: ?ArticleSupply, supplier_id: int|null, needed: numeric-string, suggested: numeric-string}>
     */
    public function suggest(Warehouse $warehouse): array {
        /** @var array<int, numeric-string> $needed */
        $needed = [];

        foreach ($this->levels->belowReorder($warehouse) as $row) {
            $variant = $row['setting']->variant;
            if (! $variant instanceof ArticleVariant) {
                continue;
            }
            $articleId = (int) $variant->article_id;
            $needed[$articleId] = bcadd($needed[$articleId] ?? '0', $row['shortfall'], self::SCALE);
        }

        foreach (ProcurementRequest::query()->where('status', ProcurementStatus::Open->value)->get() as $request) {
            $articleId = (int) $request->article_id;
            $needed[$articleId] = bcadd($needed[$articleId] ?? '0', (string) $request->quantity, self::SCALE);
        }

        $suggestions = [];
        foreach ($needed as $articleId => $qty) {
            $article = Article::query()->find($articleId);
            if (! $article instanceof Article) {
                continue;
            }
            $supply = ArticleSupply::query()
                ->where('article_id', $articleId)
                ->orderByDesc('is_preferred')
                ->orderBy('id')
                ->first();

            $suggestions[] = [
                'article' => $article,
                'supply' => $supply,
                'supplier_id' => $supply?->supplier_id,
                'needed' => bcadd($qty, '0', self::SCALE),
                'suggested' => $this->roundToSupply($qty, $supply),
            ];
        }

        return $suggestions;
    }

    /**
     * Erzeugt je Lieferant eine Entwurfsbestellung aus den Vorschlägen mit
     * bekannter Bezugsquelle und markiert die berücksichtigten Anforderungen als
     * bestellt.
     *
     * @return list<PurchaseOrder>
     */
    public function createOrders(Warehouse $warehouse, Organization $organization, ?int $createdBy = null): array {
        /** @var array<int, list<array{article: Article, supply: ArticleSupply, qty: numeric-string}>> $bySupplier */
        $bySupplier = [];
        $orderedArticleIds = [];

        foreach ($this->suggest($warehouse) as $suggestion) {
            $supply = $suggestion['supply'];
            if (! $supply instanceof ArticleSupply) {
                continue;
            }
            $bySupplier[(int) $supply->supplier_id][] = ['article' => $suggestion['article'], 'supply' => $supply, 'qty' => $suggestion['suggested']];
            $orderedArticleIds[] = $suggestion['article']->id;
        }

        $created = [];
        foreach ($bySupplier as $supplierId => $items) {
            $supplier = Supplier::query()->find($supplierId);
            if (! $supplier instanceof Supplier) {
                continue;
            }
            $order = $this->orders->createDraft($organization, $supplier, $warehouse, ['created_by' => $createdBy]);
            foreach ($items as $item) {
                $this->orders->addLine($order, $item['article'], $item['qty'], [
                    'supplier_sku' => $item['supply']->supplier_sku,
                    'unit_price' => $item['supply']->purchase_price,
                ]);
            }
            $created[] = $order;
        }

        if ($orderedArticleIds !== []) {
            ProcurementRequest::query()
                ->where('status', ProcurementStatus::Open->value)
                ->whereIn('article_id', $orderedArticleIds)
                ->update(['status' => ProcurementStatus::Ordered->value]);
        }

        return $created;
    }

    /**
     * Mindestens MOQ, aufgerundet auf ein Vielfaches der Verpackungseinheit.
     *
     * @param  numeric-string  $qty
     * @return numeric-string
     */
    private function roundToSupply(string $qty, ?ArticleSupply $supply): string {
        if (! $supply instanceof ArticleSupply) {
            return bcadd($qty, '0', self::SCALE);
        }

        if (bccomp($qty, $supply->moq, self::SCALE) < 0) {
            $qty = $supply->moq;
        }

        $pack = $supply->pack_size;
        if (bccomp($pack, '0', self::SCALE) > 0 && bccomp($pack, '1', self::SCALE) !== 0) {
            $multiples = bcdiv($qty, $pack, 0);
            $covered = bcmul($multiples, $pack, self::SCALE);
            if (bccomp($covered, $qty, self::SCALE) < 0) {
                $covered = bcmul(bcadd($multiples, '1', 0), $pack, self::SCALE);
            }
            $qty = $covered;
        }

        return bcadd($qty, '0', self::SCALE);
    }
}
