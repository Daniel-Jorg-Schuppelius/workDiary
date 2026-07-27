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

use App\Models\{Material, MaterialUsage};
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
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price?->getAmount(),
            'tax_rate' => $this->tax_rate?->getNumericValue(),
            'line_total_net' => $this->line_total_net?->getAmount(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
