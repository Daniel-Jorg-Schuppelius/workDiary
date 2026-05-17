<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAssignmentResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\EmergencyAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmergencyAssignment */
class EmergencyAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'on_call_shift_id' => $this->on_call_shift_id,
            'start_at' => optional($this->start_at)->toIso8601String(),
            'end_at' => optional($this->end_at)->toIso8601String(),
            'reason' => $this->reason,
            'is_archived' => (bool) $this->is_archived,
        ];
    }
}
