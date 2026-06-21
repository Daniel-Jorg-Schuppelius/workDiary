<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasContactAndBankDetails.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

/**
 * Gemeinsame Kontakt-/Bankdaten-Helfer für "Party"-artige Modelle
 * (Kunde, Lieferant, …). Setzt die Felder bank_account_holder/bank_iban/
 * bank_bic/bank_name, contact_persons (array-cast) sowie contact_name/
 * email/phone/mobile voraus.
 */
trait HasContactAndBankDetails {
    /**
     * Aggregierte Bankverbindung für Detail-/Show-Sichten.
     *
     * @return array{has_any: bool, holder: ?string, iban: ?string, bic: ?string, bank: ?string}
     */
    public function bankDetails(): array {
        $hasAny = (string) $this->bank_iban !== ''
            || (string) $this->bank_bic !== ''
            || (string) $this->bank_name !== ''
            || (string) $this->bank_account_holder !== '';

        return [
            'has_any' => $hasAny,
            'holder' => $this->bank_account_holder,
            'iban' => $this->bank_iban,
            'bic' => $this->bank_bic,
            'bank' => $this->bank_name,
        ];
    }

    /**
     * Primärer Ansprechpartner, gemerged mit den Legacy-Einzelfeldern.
     *
     * @return array{name: ?string, email: ?string, phone: ?string}
     */
    public function primaryContact(): array {
        $persons = $this->contact_persons ?? [];
        $primary = collect($persons)->firstWhere('primary', true) ?? ($persons[0] ?? []);

        return [
            'name' => $primary['name'] ?? $this->contact_name,
            'email' => $primary['email'] ?? $this->email,
            'phone' => $primary['phone'] ?? ($this->phone ?: $this->mobile),
        ];
    }
}
