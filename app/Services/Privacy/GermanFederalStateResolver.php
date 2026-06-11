<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GermanFederalStateResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

/**
 * Liefert nur bei einem eindeutigen zweistelligen PLZ-Leitbereich ein
 * Bundesland. Grenzbereiche bleiben bewusst unaufgeloest.
 */
class GermanFederalStateResolver {
    /** @var array<string, string> */
    private const NAMES = [
        'BW' => 'Baden-Württemberg',
        'BY' => 'Bayern',
        'BE' => 'Berlin',
        'BB' => 'Brandenburg',
        'HB' => 'Bremen',
        'HH' => 'Hamburg',
        'HE' => 'Hessen',
        'MV' => 'Mecklenburg-Vorpommern',
        'NI' => 'Niedersachsen',
        'NW' => 'Nordrhein-Westfalen',
        'RP' => 'Rheinland-Pfalz',
        'SL' => 'Saarland',
        'SN' => 'Sachsen',
        'ST' => 'Sachsen-Anhalt',
        'SH' => 'Schleswig-Holstein',
        'TH' => 'Thüringen',
    ];

    /** @var array<int, string> */
    private const UNAMBIGUOUS_PREFIXES = [
        1 => 'SN', 2 => 'SN', 3 => 'BB', 4 => 'SN', 6 => 'ST', 7 => 'TH', 8 => 'SN', 9 => 'SN',
        10 => 'BE', 11 => 'BE', 12 => 'BE', 13 => 'BE', 15 => 'BB', 16 => 'BB',
        18 => 'MV', 19 => 'MV', 20 => 'HH', 21 => 'HH', 22 => 'HH',
        24 => 'SH', 25 => 'SH', 26 => 'NI', 27 => 'NI', 29 => 'NI', 30 => 'NI', 31 => 'NI',
        32 => 'NW', 33 => 'NW', 35 => 'HE', 36 => 'HE', 39 => 'ST',
        40 => 'NW', 41 => 'NW', 42 => 'NW', 43 => 'NW', 44 => 'NW', 45 => 'NW', 46 => 'NW',
        47 => 'NW', 48 => 'NW', 49 => 'NI', 50 => 'NW', 51 => 'NW', 52 => 'NW', 53 => 'NW',
        54 => 'RP', 55 => 'RP', 56 => 'RP', 58 => 'NW', 59 => 'NW',
        60 => 'HE', 61 => 'HE', 62 => 'HE', 64 => 'HE',
        67 => 'RP', 70 => 'BW', 71 => 'BW', 72 => 'BW', 73 => 'BW', 74 => 'BW',
        75 => 'BW', 76 => 'BW', 77 => 'BW', 78 => 'BW', 79 => 'BW',
        80 => 'BY', 81 => 'BY', 82 => 'BY', 83 => 'BY', 84 => 'BY', 85 => 'BY', 86 => 'BY', 87 => 'BY',
        90 => 'BY', 91 => 'BY', 92 => 'BY', 93 => 'BY', 94 => 'BY', 97 => 'BY', 99 => 'TH',
    ];

    /** @return array{code: string, name: string, postal_code: string}|null */
    public function resolve(?string $postalCode, ?string $country = null): ?array {
        if (! $this->isGermany($country)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $postalCode);
        if (! is_string($digits) || strlen($digits) !== 5) {
            return null;
        }

        $code = self::UNAMBIGUOUS_PREFIXES[(int) substr($digits, 0, 2)] ?? null;
        if ($code === null) {
            return null;
        }

        return ['code' => $code, 'name' => self::NAMES[$code], 'postal_code' => $digits];
    }

    private function isGermany(?string $country): bool {
        $normalized = mb_strtolower(trim((string) $country));

        return $normalized === '' || in_array($normalized, ['de', 'deu', 'deutschland', 'germany'], true);
    }
}
