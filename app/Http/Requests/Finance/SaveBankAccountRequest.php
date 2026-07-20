<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveBankAccountRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseFormRequest;

class SaveBankAccountRequest extends BaseFormRequest {
    protected function prepareForValidation(): void {
        // Normalisieren VOR der Validierung (Vollaudit 2026-07, M39): der
        // Dubletten-Blindindex (BankHelper::hashIBAN) hasht sonst rohen Input.
        $this->merge([
            'iban' => \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN((string) $this->input('iban', '')) ?? '',
            'bic' => strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('bic', ''))) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array {
        return [
            'label' => ['required', 'string', 'max:120'],
            'iban' => ['required', 'string', 'max:64', new \App\Rules\Iban()],
            'bic' => ['nullable', 'string', 'max:32', new \App\Rules\Bic()],
            'account_holder' => ['nullable', 'string', 'max:200'],
            'datev_account_no' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
