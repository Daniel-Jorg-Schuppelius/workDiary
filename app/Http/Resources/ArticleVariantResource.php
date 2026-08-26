<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleVariantResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\{Article, ArticleVariant};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only-Darstellung einer Artikelvariante (MVP-718).
 *
 * @mixin ArticleVariant
 */
class ArticleVariantResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'article_id' => Sqid::encode(Article::class, $this->article_id),
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'name' => $this->name,
            'option_signature' => $this->option_signature,
            'status' => $this->status->value,
            'is_default' => (bool) $this->is_default,
            'currency' => $this->currency?->value,
            'purchase_price' => $this->purchase_price?->getAmount(),
            'sale_price' => $this->sale_price?->getAmount(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
