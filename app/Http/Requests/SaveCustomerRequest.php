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

use App\Http\Requests\Concerns\{DecodesSqidInputs, PartyFormFields};
use App\Models\Customer;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use PartyFormFields;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'tag_ids' => \App\Models\Tag::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');
        $organizationId = $customer instanceof Customer
            ? $customer->organization_id
            : $this->currentOrganizationId();

        return array_merge($this->partyBaseRules(), [
            'number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('customers', 'number')
                    ->where(fn($q) => $q->where('organization_id', $organizationId))
                    ->ignore($customer?->id),
            ],
            // Kürzel für den Alias-Abgleich der Fernwartungs-Inbox (z. B. GSL).
            'matchcode' => [
                'nullable',
                'string',
                'max:16',
                Rule::unique('customers', 'matchcode')
                    ->where(fn($q) => $q->where('organization_id', $organizationId))
                    ->ignore($customer?->id),
            ],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'internal_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'invoice_text' => ['nullable', 'string', 'max:5000'],
            'billable' => ['sometimes', 'boolean'],
            // E-Rechnung (Feature 045): Leitweg-ID/Käuferreferenz (BT-10).
            'buyer_reference' => ['nullable', 'string', 'max:64'],
            // Fakturierungsweg-Override (Feature 045): nur mit finance.config
            // änderbar — ohne die Permission wird das Feld verworfen (siehe
            // prepareForValidation) und taucht nicht in validated() auf.
            'billing_mode' => ['sometimes', 'nullable', Rule::in(\App\Enums\Finance\BillingMode::values())],
            // DATEV-Debitorennummer (Feature 045, Priorität 2): nur mit
            // finance.config änderbar (siehe prepareForValidation); leer ⇒
            // deterministische Vergaberegel im DatevBookingService.
            'debtor_no' => ['sometimes', 'nullable', 'string', 'max:12'],
            // Anfahrt-Übersteuerung; leere Felder = global erben.
            'travel_settings' => ['nullable', 'array'],
            'travel_settings.mode' => ['nullable', 'in:flat,km'],
            'travel_settings.flat_amount' => ['nullable', 'numeric', 'min:0'],
            'travel_settings.rate_per_km' => ['nullable', 'numeric', 'min:0'],
            'travel_settings.km_source' => ['nullable', 'in:company,tour'],
        ]);
    }

    protected function prepareForValidation(): void {
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

        // Debitorennummer (Feature 045): identische finance.config-Härtung.
        if ($this->has('debtor_no')) {
            $canConfigureFinance = $this->user()?->can(\App\Enums\User\Permission::FinanceConfig->value) === true;
            if (! $canConfigureFinance) {
                $this->request->remove('debtor_no');
            } elseif ($this->input('debtor_no') === '') {
                $this->merge(['debtor_no' => null]);
            }
        }

        $this->merge(array_merge($this->partyNormalizedData(), [
            'billable' => $this->boolean('billable'),
            'travel_settings' => $travel === [] ? null : $travel,
        ]));
    }
}
