<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Material */
class MaterialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit' => $this->unit,
            'default_unit_price' => $this->default_unit_price !== null ? (string) $this->default_unit_price : null,
            'tax_rate' => $this->tax_rate !== null ? (string) $this->tax_rate : null,
            'is_active' => (bool) $this->is_active,
            'external_provider' => $this->external_provider,
            'external_id' => $this->external_id,
        ];
    }
}
