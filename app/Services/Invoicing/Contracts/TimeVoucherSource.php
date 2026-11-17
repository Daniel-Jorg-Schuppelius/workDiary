<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeVoucherSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Contracts;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use Carbon\CarbonInterface;

/**
 * Zeitbelege, die ein Buchhaltungsprogramm für einen Kunden aus Zeiten
 * angelegt hat (Erweiterungspunkt, MVP-1734): Fremdreferenz und
 * Leistungszeitraum. Die Reparatur alter Pauschal-Befunde liest sie, ohne das
 * Programm zu kennen; das Plugin meldet sich über `ModuleRegistry::contribute()` an.
 */
interface TimeVoucherSource {
    /** @return list<array{reference: ExternalReference, label: string, from: CarbonInterface, to: CarbonInterface}> */
    public function timeVouchers(Customer $customer): array;
}
