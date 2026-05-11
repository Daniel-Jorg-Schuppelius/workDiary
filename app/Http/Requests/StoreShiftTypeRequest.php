<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreShiftTypeRequest extends FormRequest {
    public function authorize(): bool {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name'               => ['required', 'string', 'max:100'],
            'abbreviation'       => ['required', 'string', 'max:5'],
            'color'              => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_start_time' => ['nullable', 'date_format:H:i'],
            'default_end_time'   => ['nullable', 'date_format:H:i'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }
}
