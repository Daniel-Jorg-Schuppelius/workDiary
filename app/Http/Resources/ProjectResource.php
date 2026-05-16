<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'number' => $this->number,
            'description' => $this->description,
            'color' => $this->color,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
            'parent_id' => $this->parent_id,
            'starts_on' => optional($this->starts_on)->toDateString(),
            'ends_on' => optional($this->ends_on)->toDateString(),
            'hourly_rate' => $this->hourly_rate,
            'budget' => $this->budget,
            'time_budget' => $this->time_budget,
            'billable' => (bool) $this->billable,
            'is_default' => (bool) $this->is_default,
            'archived_at' => optional($this->archived_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
