<?php

namespace App\Http\Requests;

use App\Models\ScheduledShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateScheduledShiftRequest extends FormRequest {
    public function authorize(): bool {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id'       => ['sometimes', 'integer', 'exists:users,id'],
            'shift_type_id' => ['sometimes', 'nullable', 'integer', 'exists:shift_types,id'],
            'date'          => ['sometimes', 'date'],
            'start_time'    => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time'      => ['sometimes', 'nullable', 'date_format:H:i'],
            'note'          => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status'        => ['sometimes', 'string', 'in:' . implode(',', ScheduledShift::$statuses)],
        ];
    }
}
