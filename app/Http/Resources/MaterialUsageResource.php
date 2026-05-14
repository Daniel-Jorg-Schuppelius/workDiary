<?php

namespace App\Http\Resources;

use App\Models\MaterialUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaterialUsage */
class MaterialUsageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price !== null ? (string) $this->unit_price : null,
            'tax_rate' => $this->tax_rate !== null ? (string) $this->tax_rate : null,
            'line_total_net' => (string) $this->line_total_net,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
