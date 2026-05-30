<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\{ScheduledShift, ShiftType, User};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScheduledShift */
class ScheduledShiftResource extends JsonResource {
    public function __construct(ScheduledShift $resource) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'user_id' => Sqid::encodeOrNull(User::class, $this->user_id),
            'user_name' => $this->whenLoaded('user', fn() => $this->user?->name),
            'shift_type_id' => Sqid::encodeOrNull(ShiftType::class, $this->shift_type_id),
            'shift_type' => $this->whenLoaded('shiftType', function () {
                $type = $this->shiftType;

                return $type !== null ? [
                    'id' => Sqid::encodeOrNull(ShiftType::class, $type->id),
                    'name' => $type->name,
                    'abbreviation' => $type->abbreviation,
                    'color' => $type->color,
                ] : null;
            }),
            'date' => $this->date->toDateString(),
            'start_time' => $this->resolvedStartTime(),
            'end_time' => $this->resolvedEndTime(),
            'note' => $this->note,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_tone' => $this->statusTone(),
        ];
    }
}
