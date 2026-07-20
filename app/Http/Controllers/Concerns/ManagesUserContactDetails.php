<?php
/*
 * Created on   : Tue Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagesUserContactDetails.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Models\{ContactAddress, User};

/**
 * Gemeinsame Validierungsregeln und Persistenz für die erweiterten
 * Mitarbeiterdaten (strukturierter Name, Kommunikation, Adresse, Bank).
 *
 * Wird sowohl von der Admin-Mitarbeiterverwaltung (OrgMemberController) als
 * auch vom Self-Service-Profil (ProfileController) genutzt, damit beide
 * Stellen exakt dieselben Felder behandeln.
 */
trait ManagesUserContactDetails {
    /**
     * Validierungsregeln für Namens-, Kommunikations-, Adress- und Bankfelder.
     *
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    protected function contactDetailRules(): array {
        return [
            'first_name' => ['nullable', 'string', 'max:128'],
            'middle_names' => ['nullable', 'string', 'max:128'],
            'last_name' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'mobile' => ['nullable', 'string', 'max:64'],
            'fax' => ['nullable', 'string', 'max:64'],

            'address' => ['sometimes', 'array'],
            'address.supplement' => ['nullable', 'string', 'max:255'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.zip' => ['nullable', 'string', 'max:32'],
            'address.city' => ['nullable', 'string', 'max:128'],
            'address.country_code' => ['nullable', 'string', 'size:2'],

            'bank' => ['sometimes', 'array'],
            'bank.account_holder' => ['nullable', 'string', 'max:200'],
            // Gemeinsame Format-Rules (Vollaudit 2026-07, M39).
            'bank.iban' => ['nullable', 'string', 'max:64', new \App\Rules\Iban()],
            'bank.bic' => ['nullable', 'string', 'max:32', new \App\Rules\Bic()],
            'bank.bank_name' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Übernimmt die strukturierten Namens- und Kommunikationsfelder in den
     * User. Speichert NICHT (der Aufrufer entscheidet über save()).
     *
     * @param  array<string, mixed>  $data
     */
    protected function fillUserContactFields(User $user, array $data): void {
        $user->fill([
            'first_name' => $this->blankToNull($data['first_name'] ?? null),
            'middle_names' => $this->blankToNull($data['middle_names'] ?? null),
            'last_name' => $this->blankToNull($data['last_name'] ?? null),
            'phone' => $this->blankToNull($data['phone'] ?? null),
            'mobile' => $this->blankToNull($data['mobile'] ?? null),
            'fax' => $this->blankToNull($data['fax'] ?? null),
        ]);
    }

    /**
     * Legt die primäre Adresse des Users an oder aktualisiert sie. Sind alle
     * Felder leer, wird eine bestehende primäre Adresse entfernt.
     *
     * @param  array<string, mixed>  $address
     */
    protected function syncUserAddress(User $user, array $address): void {
        $payload = [
            'supplement' => $this->blankToNull($address['supplement'] ?? null),
            'street' => $this->blankToNull($address['street'] ?? null),
            'zip' => $this->blankToNull($address['zip'] ?? null),
            'city' => $this->blankToNull($address['city'] ?? null),
            'country_code' => $this->blankToNull($address['country_code'] ?? null),
        ];

        $existing = $user->primaryAddress();

        if ($this->allBlank($payload)) {
            $existing?->delete();

            return;
        }

        if ($existing !== null) {
            $existing->update($payload);

            return;
        }

        $user->addresses()->create($payload + [
            'organization_id' => $user->organization_id,
            'kind' => ContactAddress::KIND_DEFAULT,
            'is_primary' => true,
        ]);
    }

    /**
     * Legt die primäre Bankverbindung des Users an oder aktualisiert sie.
     * Sind alle Felder leer, wird eine bestehende primäre Verbindung entfernt.
     *
     * @param  array<string, mixed>  $bank
     */
    protected function syncUserBankAccount(User $user, array $bank): void {
        $payload = [
            'account_holder' => $this->blankToNull($bank['account_holder'] ?? null),
            // Toolkit-Normalisierung (Vollaudit 2026-07, M39/N40).
            'iban' => \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN((string) ($bank['iban'] ?? '')),
            'bic' => ($b = strtoupper((string) preg_replace('/\s+/', '', (string) ($bank['bic'] ?? '')))) !== '' ? $b : null,
            'bank_name' => $this->blankToNull($bank['bank_name'] ?? null),
        ];

        $existing = $user->primaryBankAccount();

        if ($this->allBlank($payload)) {
            $existing?->delete();

            return;
        }

        if ($existing !== null) {
            $existing->update($payload);

            return;
        }

        $user->bankAccounts()->create($payload + [
            'organization_id' => $user->organization_id,
            'is_primary' => true,
        ]);
    }

    private function blankToNull(?string $value): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, string|null>  $payload
     */
    private function allBlank(array $payload): bool {
        foreach ($payload as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return true;
    }
}
