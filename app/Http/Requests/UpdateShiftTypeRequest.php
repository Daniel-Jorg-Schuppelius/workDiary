<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateShiftTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'abbreviation' => ['sometimes', 'string', 'max:5'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'default_end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
