<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLegacyUserRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $legacyUser = $this->route('user');

        return [
            'uname' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/', Rule::unique('legacy.user', 'uname')->ignore($legacyUser?->id)],
            'userpw' => ['required', 'string', 'max:15', 'not_regex:/[\x00-\x1F\x7F]/'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
