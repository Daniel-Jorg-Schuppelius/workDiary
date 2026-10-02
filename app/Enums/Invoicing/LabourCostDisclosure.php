<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabourCostDisclosure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Models\Customer\Customer;

/** Ausweis der Arbeitskosten nach § 35a EStG auf Rechnung und Angebot (MVP-1053). */
enum LabourCostDisclosure: string implements HasLabel {
    use HasOptions;

    case Off = 'off';
    case PrivateCustomers = 'private_customers';
    case Always = 'always';

    public function label(): string {
        return match ($this) {
            self::Off => (string) __('invoicing.labour_costs.disclosure.off'),
            self::PrivateCustomers => (string) __('invoicing.labour_costs.disclosure.private_customers'),
            self::Always => (string) __('invoicing.labour_costs.disclosure.always'),
        };
    }

    /** Privatkunde = weder Firmenname noch USt-IdNr.; der Beleg kann die Regel überschreiben. */
    public function appliesTo(?Customer $customer): bool {
        return match ($this) {
            self::Off => false,
            self::Always => true,
            self::PrivateCustomers => $customer !== null
                && trim((string) $customer->company) === ''
                && trim((string) $customer->vat_id) === '',
        };
    }
}
