<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Timesheet */
class TimesheetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'work_date' => optional($this->work_date)->toDateString(),
            'status' => $this->status,
            'customer_name' => $this->customer_name,
            'customer_role' => $this->customer_role,
            'customer_email' => $this->customer_email,
            'notes' => $this->notes,
            'total_minutes' => (int) $this->totals_minutes,
            'total_material_net' => (string) $this->totals_material_net,
            'signed_at' => optional($this->signed_at)->toIso8601String(),
            'signed_ip' => $this->signed_ip,
            'signature_hash' => $this->signature_hash,
            'locked_at' => optional($this->locked_at)->toIso8601String(),
            'entries' => TimeEntryResource::collection($this->whenLoaded('entries')),
            'material_usages' => MaterialUsageResource::collection($this->whenLoaded('materialUsages')),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
