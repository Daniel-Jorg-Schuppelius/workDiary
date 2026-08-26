<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only-Darstellung eines Artikels (MVP-718). Stammdaten + optionale
 * Varianten; Preise als Dezimal-String in der Artikelwährung.
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'number' => $this->number,
            'gtin' => $this->gtin,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'base_unit' => $this->base_unit,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'stockable' => (bool) $this->stockable,
            'purchasable' => (bool) $this->purchasable,
            'sellable' => (bool) $this->sellable,
            'manufacturable' => (bool) $this->manufacturable,
            'currency' => $this->currency->value,
            'default_purchase_price' => $this->default_purchase_price?->getAmount(),
            'default_sale_price' => $this->default_sale_price?->getAmount(),
            'variants' => ArticleVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
