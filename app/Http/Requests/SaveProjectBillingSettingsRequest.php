<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProjectBillingSettingsRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveProjectBillingSettingsRequest extends FormRequest {
    public function authorize(): bool {
        return $this->user()?->canManageBilling() === true;
    }

    protected function prepareForValidation(): void {
        // Leere Strings (Auswahl "Erben") als null behandeln.
        $this->merge([
            'billing_increment_minutes' => $this->filled('billing_increment_minutes')
                ? $this->input('billing_increment_minutes') : null,
            'billing_grouping_gap_minutes' => $this->input('billing_grouping_gap_minutes') === null
                || $this->input('billing_grouping_gap_minutes') === ''
                ? null : $this->input('billing_grouping_gap_minutes'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'billing_increment_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'billing_grouping_gap_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ];
    }
}
