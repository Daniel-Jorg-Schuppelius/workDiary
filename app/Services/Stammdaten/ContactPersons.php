<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactPersons.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use CommonToolkit\Helper\Data\PersonNameHelper;

/**
 * Einträge der Liste weiterer Ansprechpartner (`contact_persons`, MVP-1137).
 * Ein Eintrag trägt Anrede und Namensteile; `name` wird daraus gebildet. Kommt
 * nur `name` (API, Abgleich, Altbestand), zerlegt der Namenszerleger ihn —
 * dieselbe Regel wie beim Hauptansprechpartner.
 */
final class ContactPersons {
    private const NAME_PARTS = ['title', 'first_name', 'middle_names', 'last_name'];

    /**
     * Vollständiger Eintrag oder null, wenn er weder Namen noch E-Mail oder
     * Telefon trägt.
     *
     * @param  array<string, mixed>  $row
     * @return array{salutation: ?string, title: ?string, first_name: ?string, middle_names: ?string, last_name: ?string, name: ?string, email: ?string, phone: ?string, primary: bool}|null
     */
    public static function normalize(array $row): ?array {
        $parts = [];
        foreach (self::NAME_PARTS as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            $parts[$key] = $value !== '' ? $value : null;
        }
        $salutation = ContactSalutation::tryFrom((string) ($row['salutation'] ?? ''))?->value;

        if (array_filter($parts) === []) {
            $split = PersonNameHelper::split(is_string($row['name'] ?? null) ? $row['name'] : null);
            $salutation ??= $split['salutation'];
            $parts = array_intersect_key($split, array_flip(self::NAME_PARTS));
        }

        $name = PersonNameHelper::join($parts);
        $email = trim((string) ($row['email'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));
        if ($name === '' && $email === '' && $phone === '') {
            return null;
        }

        return [
            'salutation' => $salutation,
            ...$parts,
            'name' => $name !== '' ? $name : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'primary' => (bool) ($row['primary'] ?? false),
        ];
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function normalizeAll(iterable $rows): array {
        $list = [];
        foreach ($rows as $row) {
            $entry = is_array($row) ? self::normalize($row) : null;
            if ($entry !== null) {
                $list[] = $entry;
            }
        }

        return $list;
    }

    /**
     * Hauptansprechpartner einer Partei für Fremdsysteme: der Ansprechpartner
     * des Formulars, sonst der als primär markierte, sonst der erste Eintrag
     * der Liste.
     *
     * @return array<string, mixed>|null
     */
    public static function primaryOf(Customer|Supplier $party): ?array {
        if (trim((string) $party->contact_name) !== '') {
            return self::normalize([
                'salutation' => $party->contact_salutation?->value,
                'title' => $party->contact_title,
                'first_name' => $party->contact_first_name,
                'middle_names' => $party->contact_middle_names,
                'last_name' => $party->contact_last_name,
                'name' => $party->contact_name,
                'email' => $party->email,
                'phone' => $party->phone ?: $party->mobile,
                'primary' => true,
            ]);
        }

        $list = self::normalizeAll($party->contact_persons ?? []);
        foreach ($list as $entry) {
            if ($entry['primary']) {
                return $entry;
            }
        }

        return $list[0] ?? null;
    }

    /**
     * Name mit Anrede für die Anzeige, z. B. „Frau Dr. Anna Müller“.
     *
     * @param  array<string, mixed>  $row
     */
    public static function displayName(array $row): string {
        $salutation = ContactSalutation::tryFrom((string) ($row['salutation'] ?? ''));
        $label = $salutation !== null && $salutation !== ContactSalutation::Diverse ? $salutation->label() : '';

        return trim($label . ' ' . (string) ($row['name'] ?? ''));
    }
}
