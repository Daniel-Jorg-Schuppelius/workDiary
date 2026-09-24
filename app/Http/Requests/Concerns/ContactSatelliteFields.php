<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactSatelliteFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use CommonToolkit\Enums\CountryCode;
use Illuminate\Validation\Rule;

/**
 * Regeln für den Adress-Satelliten einer Partei (MVP-869); die Werte
 * schreibt der Controller über `WritesContactDetails` nach
 * `contact_addresses`, nie inline.
 */
trait ContactSatelliteFields {
    /** @return array<string, list<mixed>> */
    protected function addressRules(bool $withCountry = false): array {
        return [
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:32'],
            'address_city' => ['nullable', 'string', 'max:128'],
        ] + ($withCountry ? ['country' => ['nullable', 'string', Rule::enum(CountryCode::class)]] : []);
    }
}
