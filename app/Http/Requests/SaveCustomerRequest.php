<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveCustomerRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Customer, Organization};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'tag_ids' => \App\Models\Tag::class,
    ];

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');
        $organizationId = $customer instanceof Customer
            ? $customer->organization_id
            : $this->currentOrganizationId();

        return [
            'name' => ['required', 'string', 'max:200'],
            'number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('customers', 'number')
                    ->where(fn($q) => $q->where('organization_id', $organizationId))
                    ->ignore($customer?->id),
            ],
            'company' => ['nullable', 'string', 'max:200'],
            'vat_id' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'mobile' => ['nullable', 'string', 'max:64'],
            'fax' => ['nullable', 'string', 'max:64'],
            'homepage' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:32'],
            'address_city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'color' => ['nullable', 'string', 'max:16'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'internal_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'invoice_text' => ['nullable', 'string', 'max:5000'],
            'bank_account_holder' => ['nullable', 'string', 'max:200'],
            'bank_iban' => ['nullable', 'string', 'max:64', 'regex:/^[A-Z]{2}[0-9A-Z\s]{10,40}$/i'],
            'bank_bic' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/i'],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'billable' => ['sometimes', 'boolean'],
            // E-Rechnung (Feature 045): Leitweg-ID/Käuferreferenz (BT-10).
            'buyer_reference' => ['nullable', 'string', 'max:64'],
            // Fakturierungsweg-Override (Feature 045): nur mit finance.config
            // änderbar — ohne die Permission wird das Feld verworfen (siehe
            // prepareForValidation) und taucht nicht in validated() auf.
            'billing_mode' => ['sometimes', 'nullable', Rule::in(\App\Enums\Finance\BillingMode::values())],
            'contact_persons' => ['nullable', 'array', 'max:20'],
            'contact_persons.*.name' => ['nullable', 'string', 'max:200'],
            'contact_persons.*.email' => ['nullable', 'email', 'max:255'],
            'contact_persons.*.phone' => ['nullable', 'string', 'max:64'],
            'contact_persons.*.primary' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:500'],
            // Anfahrt-Übersteuerung; leere Felder = global erben.
            'travel_settings' => ['nullable', 'array'],
            'travel_settings.mode' => ['nullable', 'in:flat,km'],
            'travel_settings.flat_amount' => ['nullable', 'numeric', 'min:0'],
            'travel_settings.rate_per_km' => ['nullable', 'numeric', 'min:0'],
            'travel_settings.km_source' => ['nullable', 'in:company,tour'],
        ];
    }

    private function currentOrganizationId(): ?int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof Organization) {
                return (int) $organization->id;
            }
        }

        $user = Auth::user();

        return $user?->organization_id !== null ? (int) $user->organization_id : null;
    }

    protected function prepareForValidation(): void {
        // Leere Kontaktpersonen-Zeilen herausfiltern + primary-Flag normalisieren
        $persons = $this->input('contact_persons', []);
        if (is_array($persons)) {
            $persons = array_values(array_filter($persons, function ($row): bool {
                if (! is_array($row)) {
                    return false;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                $phone = trim((string) ($row['phone'] ?? ''));

                return $name !== '' || $email !== '' || $phone !== '';
            }));
            $persons = array_map(static function (array $row): array {
                return [
                    'name' => $row['name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'primary' => (bool) ($row['primary'] ?? false),
                ];
            }, $persons);
        } else {
            $persons = [];
        }

        // IBAN/BIC werden ohne Leerzeichen und in Großbuchstaben gespeichert,
        // damit Vergleiche und Anzeige konsistent bleiben.
        $iban = (string) preg_replace('/\s+/', '', (string) $this->input('bank_iban', ''));
        $bic = (string) preg_replace('/\s+/', '', (string) $this->input('bank_bic', ''));

        // Anfahrt-Übersteuerung: leere Werte entfernen, komplett leer ⇒ null (erben).
        $travel = $this->input('travel_settings', []);
        if (is_array($travel)) {
            $travel = array_filter($travel, static fn($v): bool => $v !== null && $v !== '');
        } else {
            $travel = [];
        }

        // Fakturierungsweg (Feature 045): ohne finance.config-Permission wird
        // die Eingabe ignoriert; leerer String = Override entfernen (erben).
        if ($this->has('billing_mode')) {
            $canConfigureFinance = $this->user()?->can(\App\Enums\User\Permission::FinanceConfig->value) === true;
            if (! $canConfigureFinance) {
                $this->request->remove('billing_mode');
            } elseif ($this->input('billing_mode') === '') {
                $this->merge(['billing_mode' => null]);
            }
        }

        $this->merge([
            'billable' => $this->boolean('billable'),
            'currency' => $this->string('currency')->upper()->value() ?: 'EUR',
            'country' => $this->string('country')->upper()->value() ?: null,
            'contact_persons' => $persons,
            'bank_iban' => $iban !== '' ? strtoupper($iban) : null,
            'bank_bic' => $bic !== '' ? strtoupper($bic) : null,
            'travel_settings' => $travel === [] ? null : $travel,
        ]);
    }
}
