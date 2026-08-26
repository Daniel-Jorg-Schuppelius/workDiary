<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\{Article, ArticleVariant, PurchaseOrder, PurchaseOrderLine};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only-Darstellung einer Bestellung (MVP-718) inkl. Positionen (wenn
 * geladen). Mengen/Preise als Dezimal-Strings.
 *
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'number' => $this->number,
            'status' => $this->status->value,
            'supplier' => $this->whenLoaded('supplier', fn(): ?array => $this->supplier === null ? null : [
                'id' => $this->supplier->sqid,
                'name' => $this->supplier->name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn(): ?array => $this->warehouse === null ? null : [
                'id' => $this->warehouse->sqid,
                'name' => $this->warehouse->name,
            ]),
            'currency' => $this->currency->value,
            'freight_cost' => $this->freight_cost?->getAmount(),
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'expected_at' => $this->expected_at?->toDateString(),
            'note' => $this->note,
            'lines' => $this->whenLoaded('lines', fn(): array => $this->lines->map(static fn(PurchaseOrderLine $line): array => [
                'id' => $line->sqid,
                'article_id' => Sqid::encode(Article::class, $line->article_id),
                'article_variant_id' => Sqid::encodeOrNull(ArticleVariant::class, $line->article_variant_id),
                'description' => $line->description,
                'supplier_sku' => $line->supplier_sku,
                'unit' => $line->unit,
                'ordered_qty' => $line->ordered_qty?->getNumericValue(),
                'received_qty' => $line->received_qty?->getNumericValue(),
                'open_qty' => $line->openQty(),
                'unit_price' => $line->unit_price?->getAmount(),
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
