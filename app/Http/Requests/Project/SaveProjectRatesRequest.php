<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProjectRatesRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Projektstufe der Satzhierarchie (MVP-482). Leeres Feld = erben
 * (Kundensatz → Organisations-Standardsatz).
 */
class SaveProjectRatesRequest extends FormRequest {
    public function authorize(): bool {
        return $this->user()?->canManageBilling() === true;
    }

    protected function prepareForValidation(): void {
        $this->merge([
            'hourly_rate' => $this->input('hourly_rate') === '' ? null : $this->input('hourly_rate'),
            'internal_rate' => $this->input('internal_rate') === '' ? null : $this->input('internal_rate'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'internal_rate' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }
}
