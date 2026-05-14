<?php

namespace App\Http\Resources;

use App\Models\ScheduledShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScheduledShift */
class ScheduledShiftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'shift_type' => $this->whenLoaded('shiftType', fn () => [
                'id' => $this->shiftType->id,
                'name' => $this->shiftType->name,
                'abbreviation' => $this->shiftType->abbreviation,
                'color' => $this->shiftType->color,
            ]),
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
