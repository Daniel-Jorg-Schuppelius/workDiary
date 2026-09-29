<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullRetainerVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\CustomerBillingStatement;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\Billing\Contracts\RetainerVoucherLinks;
use Illuminate\Validation\ValidationException;

/** Ohne Buchhaltungsprogramm: keine Belege, Verknüpfen wird abgewiesen. */
final class NullRetainerVoucherLinks implements RetainerVoucherLinks {
    public function linkedVouchers(iterable $statements): array {
        return [];
    }

    public function linkableVouchers(Customer $customer, CustomerBillingStatement $statement): array {
        return [];
    }

    public function link(CustomerBillingStatement $statement, string $key): RetainerVoucherRef {
        throw ValidationException::withMessages(['voucher' => __('customer-billing.retainer_no_channel')]);
    }

    public function unlink(CustomerBillingStatement $statement): void {}

    public function reconcile(Organization $organization): array {
        return ['booked' => 0, 'revoked' => 0, 'skipped' => 0, 'linked' => 0];
    }
}
