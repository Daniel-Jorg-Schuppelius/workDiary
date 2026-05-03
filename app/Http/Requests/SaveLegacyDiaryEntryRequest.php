<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveLegacyDiaryEntryRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'inhalt' => ['required', 'string', 'max:65535'],
            'antwort' => ['nullable', 'string', 'max:65535'],
            'gelesen' => ['required', 'integer', 'in:-1,1,2,3'],
            'von' => ['nullable', 'date'],
            'bis' => ['nullable', 'date', 'after_or_equal:von'],
            'sms' => ['nullable', 'in:j'],
            'user' => ['nullable', 'integer', 'min:4', 'exists:legacy.user,id'],
        ];
    }
}
