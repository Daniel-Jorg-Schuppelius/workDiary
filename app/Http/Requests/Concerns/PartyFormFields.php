<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyFormFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Concerns;

use App\Models\Platform\Organization;
use App\Services\Stammdaten\ContactPersons;
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsame Validierungs-/Normalisierungslogik für "Party"-FormRequests
 * (Kunde, Lieferant, …): Stammdaten, Adresse, Kontakt, Bankverbindung und
 * Kontaktpersonen. Entitätsspezifische Felder (z. B. number-Unique, billable,
 * vendor_number) ergänzt der jeweilige Request über array_merge.
 */
trait PartyFormFields {
    use ContactNameFields;
    use ContactSatelliteFields;

    /**
     * Geteilte Regeln (ohne das entitätsspezifische `number`-Unique).
     *
     * @return array<string, mixed>
     */
    protected function partyBaseRules(): array {
        return $this->addressRules(withCountry: true) + $this->contactNameRules() + [
            'name' => ['required', 'string', 'max:200'],
            'company' => ['nullable', 'string', 'max:200'],
            'vat_id' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'mobile' => ['nullable', 'string', 'max:64'],
            'fax' => ['nullable', 'string', 'max:64'],
            'homepage' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', \Illuminate\Validation\Rule::enum(\CommonToolkit\Enums\CurrencyCode::class)],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'color' => ['nullable', 'string', 'max:16', new \App\Rules\ColorValue],
            'comment' => ['nullable', 'string', 'max:5000'],
            'bank_account_holder' => ['nullable', 'string', 'max:200'],
            // Gemeinsame Format-Rules (Vollaudit 2026-07, M39).
            'bank_iban' => ['nullable', 'string', 'max:64', new \App\Rules\Iban()],
            'bank_bic' => ['nullable', 'string', 'max:32', new \App\Rules\Bic()],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'contact_persons' => ['nullable', 'array', 'max:20'],
            'contact_persons.*.salutation' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\Stammdaten\ContactSalutation::class)],
            'contact_persons.*.title' => ['nullable', 'string', 'max:64'],
            'contact_persons.*.first_name' => ['nullable', 'string', 'max:128'],
            'contact_persons.*.middle_names' => ['nullable', 'string', 'max:128'],
            'contact_persons.*.last_name' => ['nullable', 'string', 'max:128'],
            'contact_persons.*.name' => ['nullable', 'string', 'max:200'],
            'contact_persons.*.email' => ['nullable', 'email', 'max:255'],
            'contact_persons.*.phone' => ['nullable', 'string', 'max:64'],
            'contact_persons.*.primary' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('tags')],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Kontaktpersonen normalisieren: leere Zeilen entfernen, Name aus den Teilen
     * bilden bzw. einen bloßen Namen zerlegen (MVP-1137), primary als bool.
     *
     * @return list<array<string, mixed>>
     */
    protected function normalizedContactPersons(): array {
        $persons = $this->input('contact_persons', []);

        return is_array($persons) ? ContactPersons::normalizeAll($persons) : [];
    }

    /**
     * Geteilte Normalisierung für prepareForValidation (Currency/Country in
     * Großbuchstaben, Kontaktpersonen gefiltert, IBAN/BIC ohne Leerzeichen).
     *
     * @return array<string, mixed>
     */
    protected function partyNormalizedData(): array {
        // IBAN/BIC über die Toolkit-Normalisierer (Vollaudit 2026-07, N40) —
        // Whitespace-Strip + Uppercase, leer → null.
        return [
            'currency' => $this->string('currency')->upper()->value() ?: 'EUR',
            'country' => $this->string('country')->upper()->value() ?: null,
            'contact_persons' => $this->normalizedContactPersons(),
            'bank_iban' => \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN((string) $this->input('bank_iban', '')),
            'bank_bic' => \CommonToolkit\Helper\Data\BankHelper::normalizeBIC((string) $this->input('bank_bic', '')),
        ];
    }

    protected function currentOrganizationId(): ?int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof Organization) {
                return (int) $organization->id;
            }
        }

        $user = Auth::user();

        return $user?->organization_id !== null ? (int) $user->organization_id : null;
    }
}
