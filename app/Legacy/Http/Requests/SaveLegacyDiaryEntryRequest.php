<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveLegacyDiaryEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveLegacyDiaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
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
