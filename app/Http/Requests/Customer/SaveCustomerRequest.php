<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveCustomerRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Customer;

use App\Enums\Invoicing\EInvoiceRecipientProfile;
use App\Enums\Numbering\NumberScope;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, PartyFormFields};
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\{LeitwegIdCheck, RecipientProfiles};
use App\Services\Numbering\NumberAuthority;
use ERechnungToolkit\Entities\LeitwegId;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use PartyFormFields;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'tag_ids' => \App\Models\Classification\Tag::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');
        $organizationId = $customer instanceof Customer
            ? $customer->organization_id
            : $this->currentOrganizationId();

        return array_merge($this->partyBaseRules(), $this->shippingAddressRules(), [
            'number' => [
                // Vergibt ein Buchhaltungsprogramm die Nummer, ist das Feld gesperrt (MVP-1134).
                Rule::prohibitedIf(fn (): bool => app(NumberAuthority::class)->numberLockedBy(NumberScope::Customer, $organizationId, $customer?->number_source) !== null),
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
            // Belegsprache (Feature 034, MVP-721): leer = wie Organisation.
            'document_locale' => ['nullable', 'string', Rule::in(\App\Support\Locales::enabledCodes())],
            'billable' => ['sometimes', 'boolean'],
            'exclude_from_reports' => ['sometimes', 'boolean'],
            'no_bulk_mail' => ['sometimes', 'boolean'],
            'survey_opt_out' => ['sometimes', 'boolean'],
            // Leistungsempfänger schuldet die Steuer (§ 13b UStG, MVP-1622).
            // § 13b Abs. 5 UStG: Steuerschuldner ist nur ein Unternehmer, nie ein Verbraucher (MVP-1688).
            'is_reverse_charge_recipient' => ['sometimes', 'boolean', 'declined_if:is_consumer,true'],
            // Verbraucher (MVP-1688): Leistungsort nach § 3a Abs. 1/4 UStG; fehlt das Feld (API), bleibt der Wert.
            'is_consumer' => ['sometimes', 'boolean'],
            // E-Rechnung (Feature 045): Leitweg-ID/Käuferreferenz (BT-10).
            'buyer_reference' => ['nullable', 'string', 'max:64'],
            // Registerangabe (BT-47, MVP-1686), Freitext wie die eigene Angabe der Organisation.
            'register_number' => ['nullable', 'string', 'max:200'],
            // Kundengruppe (MVP-949).
            'customer_group_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::in(app(\App\Services\Classification\ClassificationResolver::class)->list($organizationId, \App\Enums\Classification\ClassificationDomain::CustomerGroup)->modelKeys())],
            // Peppol-Empfängerkennung (Feature 066, MVP-734): `<ICD>:<Kennung>`.
            'peppol_participant_id' => ['nullable', 'string', 'max:64', new \App\Rules\PeppolParticipantId],
            'delivery_format' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\Invoicing\InvoiceDeliveryFormat::class)],
            // Empfängerprofil der E-Rechnung (Feature 196): leer = Vorgabe der Organisation.
            'einvoice_recipient_profile' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\Invoicing\EInvoiceRecipientProfile::class)],
            'einvoice_attach_activity_report' => ['nullable', 'in:0,1'],
            'einvoice_attachment_limit_mb' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Versandweg extern ausgestellter Rechnungen (Feature 196): leer = Vorgabe der Organisation.
            'einvoice_dispatch_route' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\Invoicing\ExternalDispatchRoute::class)],
            // Zahlungsbedingungen (MVP-996): leer = Vorgabe der Organisation.
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'skonto_percent' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_with:skonto_days'],
            'skonto_days' => ['nullable', 'integer', 'min:1', 'max:365', 'required_with:skonto_percent'],
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
        if ($this->filled('customer_group_id')) {
            $this->merge(['customer_group_id' => \App\Support\Sqid::decodeOrNumeric(\App\Models\Classification\Classification::class, $this->string('customer_group_id')->toString())]);
        }
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

        // Versandweg externer Rechnungen (Feature 196): identische finance.config-Härtung.
        if ($this->has('einvoice_dispatch_route')) {
            if ($this->user()?->can(\App\Enums\User\Permission::FinanceConfig->value) !== true) {
                $this->request->remove('einvoice_dispatch_route');
            } elseif ($this->input('einvoice_dispatch_route') === '') {
                $this->merge(['einvoice_dispatch_route' => null]);
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

        if ($this->input('document_locale') === '') {
            $this->merge(['document_locale' => null]);
        }
        // Nur gesendet normalisieren: Fehlt das Feld (API), bleibt die Lieferanschrift unberührt (MVP-1689).
        if ($this->has('shipping_country')) {
            $this->merge(['shipping_country' => $this->string('shipping_country')->trim()->upper()->value() ?: null]);
        }

        // Leitweg-ID (MVP-1687): vereinheitlicht gespeichert; eine ungültige meldet leitwegIdWarning(), sperrt aber nicht.
        if ($this->buyerReferenceIsLeitwegId()) {
            $this->merge(['buyer_reference' => LeitwegId::normalize((string) $this->input('buyer_reference'))]);
        }

        if ($this->has('is_consumer')) {
            $this->merge(['is_consumer' => $this->boolean('is_consumer')]);
        }

        $this->merge(array_merge($this->partyNormalizedData(), [
            'billable' => $this->boolean('billable'),
            'exclude_from_reports' => $this->boolean('exclude_from_reports'),
            'no_bulk_mail' => $this->boolean('no_bulk_mail'),
            'survey_opt_out' => $this->boolean('survey_opt_out'),
            'is_reverse_charge_recipient' => $this->boolean('is_reverse_charge_recipient'),
            'travel_settings' => $travel === [] ? null : $travel,
        ]));
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'is_reverse_charge_recipient.declined_if' => (string) __('invoicing.consumer.reverse_charge_conflict'),
        ];
    }

    /**
     * Warnung zur Käuferreferenz (MVP-1687), wenn sie als Leitweg-ID gilt und
     * Form oder Prüfziffer nicht stimmen; BT-10 bleibt sonst frei, weil private
     * Kunden dort eigene Referenzen führen.
     */
    public function leitwegIdWarning(): ?string {
        $problem = $this->buyerReferenceIsLeitwegId() ? LeitwegIdCheck::problem((string) $this->input('buyer_reference')) : null;

        return $problem !== null ? (string) __('invoicing.buyer_reference_leitweg_invalid', ['reason' => $problem]) : null;
    }

    /** Als Leitweg-ID gilt die Käuferreferenz, wenn sie wie eine aussieht oder der Kunde öffentlicher Auftraggeber ist. */
    private function buyerReferenceIsLeitwegId(): bool {
        $reference = trim((string) $this->input('buyer_reference', ''));
        if ($reference === '') {
            return false;
        }
        if (LeitwegId::looksLike($reference)) {
            return true;
        }
        $customer = $this->route('customer');
        $organization = $customer instanceof Customer
            ? $customer->organization
            : Organization::query()->find($this->currentOrganizationId());
        $profile = EInvoiceRecipientProfile::tryFrom((string) $this->input('einvoice_recipient_profile', ''))
            ?? app(RecipientProfiles::class)->for(null, $organization);

        return $profile === EInvoiceRecipientProfile::PublicSector;
    }
}
