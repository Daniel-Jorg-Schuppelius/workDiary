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
     * Die Inline-Projektionsspalten auf customers/suppliers, wie sie in
     * Formularen und gemappten Import-Wertesätzen heißen.
     *
     * @var list<string>
     */
    public const INLINE_FIELDS = [
        'address_street', 'address_zip', 'address_city', 'country',
        'bank_account_holder', 'bank_iban', 'bank_bic', 'bank_name',
    ];

    /** Inline-Spalte → Schlüssel von {@see writeAddress}. */
    private const ADDRESS_MAP = [
        'address_street' => 'street',
        'address_zip' => 'zip',
        'address_city' => 'city',
        'country' => 'country',
    ];

    /** Inline-Spalte → Schlüssel von {@see writeBankAccount}. */
    private const BANK_MAP = [
        'bank_account_holder' => 'account_holder',
        'bank_iban' => 'iban',
        'bank_bic' => 'bic',
        'bank_name' => 'bank_name',
    ];

    /**
     * Nimmt die Inline-Kontaktfelder aus einem Wertesatz heraus (Referenz wird
     * um sie erleichtert) und gibt sie zurück — damit ein Import den Rest wie
     * bisher per fill()/create() schreiben kann, die Kontaktdaten aber über
     * {@see writeInline} in contact_addresses/contact_bank_accounts landen.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function pullInline(array &$attributes): array {
        $fields = array_intersect_key($attributes, array_flip(self::INLINE_FIELDS));
        foreach (self::INLINE_FIELDS as $field) {
            unset($attributes[$field]);
        }

        return $fields;
    }

    /**
     * Teil-Update über die Inline-Spaltennamen: NUR übergebene Schlüssel
     * ändern sich, fehlende behalten den Stand der primären Adresse/Bank.
     * Importe liefern immer nur die abweichenden Felder — ein Voll-Overwrite
     * (wie im Formularpfad) würde den Rest der Adresse leeren.
     *
     * @param  array<string, mixed>  $fields
     */
    public function writeInline(Customer|Supplier $contact, array $fields): void {
        $address = $this->mapPresent($fields, self::ADDRESS_MAP);
        if ($address !== []) {
            $primary = $contact->primaryAddress();
            $this->writeAddress($contact, $address + [
                'street' => $primary?->street,
                'supplement' => $primary?->supplement,
                'zip' => $primary?->zip,
                'city' => $primary?->city,
                'country' => $primary?->country_code,
            ]);
        }

        $bank = $this->mapPresent($fields, self::BANK_MAP);
        if ($bank !== []) {
            $primary = $contact->primaryBankAccount();
            $this->writeBankAccount($contact, $bank + [
                'account_holder' => $primary?->account_holder,
                'iban' => $primary?->iban,
                'bic' => $primary?->bic,
                'bank_name' => $primary?->bank_name,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $map
     * @return array<string, ?string>
     */
    private function mapPresent(array $fields, array $map): array {
        $out = [];
        foreach ($map as $inline => $key) {
            if (array_key_exists($inline, $fields)) {
                $out[$key] = $fields[$inline] === null ? null : (string) $fields[$inline];
            }
        }

        return $out;
    }

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
