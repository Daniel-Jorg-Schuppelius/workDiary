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

use Illuminate\Foundation\Http\FormRequest;

class SaveBankAccountRequest extends FormRequest {
    public function authorize(): bool {
        return true; // Gate-Prüfung im Controller (BankAccountPolicy).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array {
        return [
            'label' => ['required', 'string', 'max:120'],
            'iban' => ['required', 'string', 'max:64'],
            'bic' => ['nullable', 'string', 'max:32'],
            'account_holder' => ['nullable', 'string', 'max:200'],
            'datev_account_no' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
