<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LegacyCallcenterLoginRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
