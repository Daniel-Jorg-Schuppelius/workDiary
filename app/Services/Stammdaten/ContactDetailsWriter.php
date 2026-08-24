<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactDetailsWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Models\{ContactAddress, Customer, Supplier};

/**
 * Schreibweg für Kontakt-Adresse/-Bankverbindung (Vollscan 2026-08-23, F8,
 * Entscheid E6): `contact_addresses`/`contact_bank_accounts` sind die EINE
 * Quelle; die Inline-Spalten (customers/suppliers address_… & bank_…) füllt
 * ausschließlich {@see \App\Observers\ContactDetailsProjectionObserver} als
 * Lese-Projektion nach. Verschlüsselte Felder nie als '' speichern
 * (DecryptException — Memory „Leere encrypted-Strings").
 */
class ContactDetailsWriter {
    /**
     * Primäre Rechnungsadresse upserten. Alles leer + keine Adresse ⇒ no-op;
     * alles leer + Adresse vorhanden ⇒ Felder werden geleert (null).
     *
     * @param  array{street?: ?string, supplement?: ?string, zip?: ?string, city?: ?string, country?: ?string}  $fields
     */
    public function writeAddress(Customer|Supplier $contact, array $fields): void {
        $values = [
            'street' => $this->nullIfBlank($fields['street'] ?? null),
            'supplement' => $this->nullIfBlank($fields['supplement'] ?? null),
            'zip' => $this->nullIfBlank($fields['zip'] ?? null),
            'city' => $this->nullIfBlank($fields['city'] ?? null),
            'country_code' => $this->nullIfBlank($fields['country'] ?? null),
        ];

        $primary = $contact->primaryAddress();
        if ($primary === null && array_filter($values) === []) {
            return;
        }

        if ($primary === null) {
            $contact->addresses()->create($values + [
                'organization_id' => $contact->organization_id,
                'kind' => ContactAddress::KIND_BILLING,
                'is_primary' => true,
            ]);

            return;
        }

        $primary->fill($values)->save();
    }

    /**
     * Primäre Bankverbindung upserten (Semantik wie {@see writeAddress}).
     *
     * @param  array{account_holder?: ?string, iban?: ?string, bic?: ?string, bank_name?: ?string}  $fields
     */
    public function writeBankAccount(Customer|Supplier $contact, array $fields): void {
        $values = [
            'account_holder' => $this->nullIfBlank($fields['account_holder'] ?? null),
            'iban' => $this->nullIfBlank($fields['iban'] ?? null),
            'bic' => $this->nullIfBlank($fields['bic'] ?? null),
            'bank_name' => $this->nullIfBlank($fields['bank_name'] ?? null),
        ];

        $primary = $contact->primaryBankAccount();
        if ($primary === null && array_filter($values) === []) {
            return;
        }

        if ($primary === null) {
            $contact->bankAccounts()->create($values + [
                'organization_id' => $contact->organization_id,
                'is_primary' => true,
            ]);

            return;
        }

        $primary->fill($values)->save();
    }

    private function nullIfBlank(?string $value): ?string {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
