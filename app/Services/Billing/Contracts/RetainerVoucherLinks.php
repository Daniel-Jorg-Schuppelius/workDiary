<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Billing\CustomerBillingStatement;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\Billing\RetainerVoucherRef;
use Illuminate\Validation\ValidationException;

/**
 * Belege des Buchhaltungsprogramms an Pauschal-Monaten (Feature 098,
 * MVP-1027): im Programm erstellte Pauschalen werden einem Monat zugeordnet,
 * der Zahlstatus fließt in den Leistungssaldo zurück. Der Kern sieht nur
 * {@see RetainerVoucherRef}; die Zuordnung hält das Plugin (ExternalReference).
 */
interface RetainerVoucherLinks {
    /**
     * Verknüpfte Belege je Monat.
     *
     * @param  iterable<CustomerBillingStatement>  $statements
     * @return array<int, RetainerVoucherRef> Monats-ID → Beleg
     */
    public function linkedVouchers(iterable $statements): array;

    /**
     * Zuordenbare Belege des Kunden (noch an keinem anderen Monat).
     *
     * @return list<RetainerVoucherRef>
     */
    public function linkableVouchers(Customer $customer, CustomerBillingStatement $statement): array;

    /**
     * Beleg (Formularschlüssel aus {@see linkableVouchers()}) an den Monat hängen.
     *
     * @throws ValidationException unbekannter, fremder oder schon vergebener Beleg
     */
    public function link(CustomerBillingStatement $statement, string $key): RetainerVoucherRef;

    /** Verknüpfung lösen und die daraus gebuchte Zahlung zurücknehmen. */
    public function unlink(CustomerBillingStatement $statement): void;

    /**
     * Zahlstatus abgleichen (und eindeutige Belege automatisch zuordnen).
     *
     * @return array{booked: int, revoked: int, skipped: int, linked: int}
     */
    public function reconcile(Organization $organization): array;
}
