<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WritesContactDetails.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\{Customer, Supplier};
use App\Services\Stammdaten\ContactDetailsWriter;

/**
 * Formular-Naht für F8/E6 (Vollscan 2026-08-23): Die address_…/bank_…-Inputs
 * der Party-Formulare gehen NICHT mehr inline aufs Model, sondern über den
 * {@see ContactDetailsWriter} in contact_addresses/contact_bank_accounts;
 * die Projektion füllt die Inline-Spalten nach. Liefert die real geänderten
 * Inline-Felder, damit der Lexoffice-Push (PUSHED_FIELDS) weiter anspringt.
 */
trait WritesContactDetails {
    /** @var list<string> */
    private static array $contactDetailFields = ContactDetailsWriter::INLINE_FIELDS;

    /**
     * Nimmt die Kontakt-Felder aus den validierten Daten heraus.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> die entnommene Kontakt-Teilmenge
     */
    private function pullContactDetails(array &$data): array {
        $fields = array_intersect_key($data, array_flip(self::$contactDetailFields));
        foreach (self::$contactDetailFields as $field) {
            unset($data[$field]);
        }

        return $fields;
    }

    /**
     * Schreibt die Kontakt-Felder über den Writer und liefert die Namen der
     * dadurch real geänderten Inline-Spalten (Projektion inklusive refresh).
     *
     * @param  array<string, mixed>  $fields
     * @return list<string>
     */
    private function writeContactDetails(Customer|Supplier $contact, array $fields): array {
        if ($fields === []) {
            return [];
        }

        $before = [];
        foreach (self::$contactDetailFields as $field) {
            $before[$field] = (string) ($contact->{$field} ?? '');
        }

        $writer = app(ContactDetailsWriter::class);
        $writer->writeAddress($contact, [
            'street' => $fields['address_street'] ?? null,
            'zip' => $fields['address_zip'] ?? null,
            'city' => $fields['address_city'] ?? null,
            'country' => $fields['country'] ?? null,
        ]);
        $writer->writeBankAccount($contact, [
            'account_holder' => $fields['bank_account_holder'] ?? null,
            'iban' => $fields['bank_iban'] ?? null,
            'bic' => $fields['bank_bic'] ?? null,
            'bank_name' => $fields['bank_name'] ?? null,
        ]);

        $contact->refresh();

        $changed = [];
        foreach (self::$contactDetailFields as $field) {
            if ((string) ($contact->{$field} ?? '') !== $before[$field]) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
