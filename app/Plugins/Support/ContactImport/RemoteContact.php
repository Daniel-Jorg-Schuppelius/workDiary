<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteContact.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ContactImport;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;

/**
 * Ein Kontakt eines Buchhaltungsprogramms in der Sprache der Stammdaten
 * (MVP-1502). `$numbers` nennt je Rolle (Kunde, Lieferant) die Nummer im
 * Programm; ein Kontakt ohne Rolle wird übersprungen.
 */
final readonly class RemoteContact {
    /**
     * @param  array<class-string<Customer|Supplier>, string|null>  $numbers  Rolle → Nummer im Programm
     * @param  array<string, mixed>  $attributes  gemappte Felder (`name`, `vat_id`, `address_*`, `contact_*` …)
     * @param  array<string, mixed>  $raw  Rohantwort (Inbox-Snapshot)
     */
    public function __construct(
        public string $externalId,
        public array $numbers,
        public array $attributes,
        public array $raw = [],
    ) {}
}
