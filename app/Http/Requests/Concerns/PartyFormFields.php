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

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsame Validierungs-/Normalisierungslogik für "Party"-FormRequests
 * (Kunde, Lieferant, …): Stammdaten, Adresse, Kontakt, Bankverbindung und
 * Kontaktpersonen. Entitätsspezifische Felder (z. B. number-Unique, billable,
 * vendor_number) ergänzt der jeweilige Request über array_merge.
 */
trait PartyFormFields {
    /**
     * Geteilte Regeln (ohne das entitätsspezifische `number`-Unique).
     *
     * @return array<string, mixed>
     */
    protected function partyBaseRules(): array {
        return [
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
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:32'],
            'address_city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'color' => ['nullable', 'string', 'max:16'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'bank_account_holder' => ['nullable', 'string', 'max:200'],
            'bank_iban' => ['nullable', 'string', 'max:64', 'regex:/^[A-Z]{2}[0-9A-Z\s]{10,40}$/i'],
            'bank_bic' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/i'],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'contact_persons' => ['nullable', 'array', 'max:20'],
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
     * Kontaktpersonen normalisieren: leere Zeilen entfernen, primary als bool.
     *
     * @return array<int, array{name: ?string, email: ?string, phone: ?string, primary: bool}>
     */
    protected function normalizedContactPersons(): array {
        $persons = $this->input('contact_persons', []);
        if (! is_array($persons)) {
            return [];
        }

        $persons = array_values(array_filter($persons, static function ($row): bool {
            if (! is_array($row)) {
                return false;
            }

            return trim((string) ($row['name'] ?? '')) !== ''
                || trim((string) ($row['email'] ?? '')) !== ''
                || trim((string) ($row['phone'] ?? '')) !== '';
        }));

        return array_map(static fn(array $row): array => [
            'name' => $row['name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'primary' => (bool) ($row['primary'] ?? false),
        ], $persons);
    }

    /**
     * Geteilte Normalisierung für prepareForValidation (Currency/Country in
     * Großbuchstaben, Kontaktpersonen gefiltert, IBAN/BIC ohne Leerzeichen).
     *
     * @return array<string, mixed>
     */
    protected function partyNormalizedData(): array {
        $iban = (string) preg_replace('/\s+/', '', (string) $this->input('bank_iban', ''));
        $bic = (string) preg_replace('/\s+/', '', (string) $this->input('bank_bic', ''));

        return [
            'currency' => $this->string('currency')->upper()->value() ?: 'EUR',
            'country' => $this->string('country')->upper()->value() ?: null,
            'contact_persons' => $this->normalizedContactPersons(),
            'bank_iban' => $iban !== '' ? strtoupper($iban) : null,
            'bank_bic' => $bic !== '' ? strtoupper($bic) : null,
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
