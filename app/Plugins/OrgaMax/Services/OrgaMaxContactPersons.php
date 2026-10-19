<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxContactPersons.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Services\Stammdaten\ContactPersons;
use Orgamax\Entities\Customers\{ContactPerson, Customer};
use Orgamax\Entities\Suppliers\Supplier;

/**
 * Ansprechpartner eines orgaMAX-Kunden oder -Lieferanten als Einträge für
 * contact_persons und als Hauptansprechpartner: der als `isMainContact`
 * markierte, sonst der erste; ein Kunde ohne Liste, aber mit eigenen
 * Namensfeldern (Privatperson), ist selbst der Ansprechpartner. Anrede steht
 * in orgaMAX als Wort („Herr“), der Titel getrennt.
 */
final class OrgaMaxContactPersons {
    /** @return array{contact: array<string, mixed>|null, persons: list<array<string, mixed>>} */
    public static function of(Customer|Supplier $party): array {
        $persons = [];
        foreach ($party->getContactPersons() ?? [] as $person) {
            $entry = $person instanceof ContactPerson
                ? ContactPersons::fromExternal($person->getSalutation(), $person->getFirstName(), $person->getLastName(), $person->getTitle(), $person->getEmail(), $person->getPhone1() ?: $person->getMobile(), $person->isMainContact())
                : (is_array($person) ? ContactPersons::fromExternal(
                    self::text($person['salutation'] ?? null),
                    self::text($person['firstName'] ?? null),
                    self::text($person['lastName'] ?? null),
                    self::text($person['title'] ?? null),
                    self::text($person['email'] ?? null),
                    self::text($person['phone1'] ?? null) ?? self::text($person['mobile'] ?? null),
                    (bool) ($person['isMainContact'] ?? false),
                ) : null);
            if ($entry !== null) {
                $persons[] = $entry;
            }
        }

        $contact = null;
        foreach ($persons as $entry) {
            if ($entry['primary']) {
                $contact = $entry;
                break;
            }
        }
        $contact ??= $persons[0] ?? null;
        if ($contact === null && $party instanceof Customer && trim((string) $party->getLastName()) !== '') {
            $contact = ContactPersons::fromExternal($party->getSalutation(), $party->getFirstName(), $party->getLastName(), $party->getTitle(), primary: true);
        }

        return ['contact' => $contact, 'persons' => $persons];
    }

    /**
     * Stammsatzfelder für den Integrations-Resolver (nur gefüllte): die Teile
     * des Hauptansprechpartners inline, die Liste als contact_persons.
     *
     * @return array<string, mixed>
     */
    public static function mapped(Customer|Supplier $party): array {
        ['contact' => $contact, 'persons' => $persons] = self::of($party);
        $fields = [];
        foreach (['salutation' => 'contact_salutation', 'title' => 'contact_title', 'first_name' => 'contact_first_name', 'middle_names' => 'contact_middle_names', 'last_name' => 'contact_last_name'] as $part => $column) {
            if (($contact[$part] ?? null) !== null) {
                $fields[$column] = $contact[$part];
            }
        }
        if ($persons !== []) {
            $fields['contact_persons'] = $persons;
        }

        return $fields;
    }

    private static function text(mixed $value): ?string {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
