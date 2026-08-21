<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSepaMandateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\Finance\MandateKind;
use App\Enums\User\Permission;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Mandatsanlage (Feature 120, MVP-609). */
class SaveSepaMandateRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['customer_id' => \App\Models\Customer::class];

    public function authorize(): bool {
        return Gate::allows(Permission::FinancePaymentRun->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'customer_id' => ['required', new ExistsInCurrentOrganization('customers')],
            // Die Mandatsreferenz ist der Schlüssel gegenüber der Bank —
            // SEPA lässt nur 35 Zeichen aus einem engen Zeichenvorrat zu.
            'reference' => [
                'required', 'string', 'max:35', 'regex:/^[A-Za-z0-9\+\?\/\-:\(\)\.,\x27 ]+$/',
                Rule::unique('sepa_mandates', 'reference')->where('organization_id', (int) $this->user()?->organization_id),
            ],
            'kind' => ['required', Rule::in(array_column(MandateKind::cases(), 'value'))],
            'signed_on' => ['required', 'date', 'before_or_equal:today'],
            'iban' => ['required', 'string', 'max:40'],
            'bic' => ['nullable', 'string', 'max:20'],
            'account_holder' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:191'],
        ];
    }
}
