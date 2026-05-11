<?php

namespace App\Http\Requests;

use App\Models\ScheduledShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreScheduledShiftRequest extends FormRequest {
    public function authorize(): bool {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'shift_type_id' => ['nullable', 'integer', 'exists:shift_types,id'],
            'date'          => ['required', 'date'],
            'start_time'    => ['nullable', 'date_format:H:i'],
            'end_time'      => ['nullable', 'date_format:H:i'],
            'note'          => ['nullable', 'string', 'max:1000'],
            'status'        => ['sometimes', 'string', 'in:' . implode(',', ScheduledShift::$statuses)],
        ];
    }
}
