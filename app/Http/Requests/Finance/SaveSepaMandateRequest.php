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

    protected function prepareForValidation(): void {
        // Normalisieren VOR der Validierung wie beim eigenen Bankkonto (M39):
        // iban_hash hasht sonst „DE12 …" und „DE12…" verschieden, und die
        // pain.008-Datei bekäme die IBAN samt Leerzeichen (Vollscan 2026-08-23, E2).
        $this->merge([
            'iban' => \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN((string) $this->input('iban', '')) ?? '',
            'bic' => strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('bic', ''))) ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'customer_id' => ['required', new ExistsInCurrentOrganization('customers')],
            // Die Mandatsreferenz ist der Schlüssel gegenüber der Bank —
            // SEPA lässt nur 35 Zeichen aus einem engen Zeichenvorrat zu.
            'reference' => [
                'required', 'string', 'max:35', 'regex:/^[A-Za-z0-9\+\?\/\-:\(\)\.,\x27 ]+$/',
                Rule::unique('sepa_mandates', 'reference')->where('organization_id', $this->currentOrganizationId()),
            ],
            'kind' => ['required', Rule::in(array_column(MandateKind::cases(), 'value'))],
            'signed_on' => ['required', 'date', 'before_or_equal:today'],
            // Strikt (mod 97 + Länderlänge): ein neues Mandat geht in die
            // pain.008-Datei, eine formal gültige, aber falsche IBAN würde erst
            // bei der Bank scheitern — hier gibt es keinen Bestand zu schonen.
            'iban' => ['required', 'string', 'max:40', new \App\Rules\Iban(), function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! \CommonToolkit\Helper\Data\BankHelper::validateIBAN($value, true)) {
                    $fail((string) __('validation.regex'));
                }
            }],
            'bic' => ['nullable', 'string', 'max:20', new \App\Rules\Bic()],
            'account_holder' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * Die gebundene Organisation, nicht `users.organization_id`: ein
     * Plattform-Admin arbeitet per Session-Override in einer fremden Org
     * (Vollscan 2026-08-23, E6).
     */
    private function currentOrganizationId(): int {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        return $organization instanceof \App\Models\Organization ? (int) $organization->id : 0;
    }
}
