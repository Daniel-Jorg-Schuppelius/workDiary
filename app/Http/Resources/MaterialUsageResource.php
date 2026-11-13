<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsageResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\Article\ArticleVariant;
use App\Models\Inventory\Warehouse;
use App\Models\Material\{Material, MaterialUsage};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaterialUsage */
class MaterialUsageResource extends JsonResource {
    public function __construct(MaterialUsage $resource) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'material_id' => Sqid::encodeOrNull(Material::class, $this->material_id),
            // Gewählte Variante (MVP-1690): null = Standardvariante des Artikels.
            'article_variant_id' => Sqid::encodeOrNull(ArticleVariant::class, $this->article_variant_id),
            'variant' => $this->variantLabel(),
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'tax_rate' => $this->tax_rate,
            'line_total_net' => $this->line_total_net,
            // Bestandswirkung (MVP-1679): null = nicht lagergeführt.
            'stock_status' => $this->stock_status?->value,
            'stock_warehouse_id' => Sqid::encodeOrNull(Warehouse::class, $this->stock_warehouse_id),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
