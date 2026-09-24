<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullContactPushTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten\Contracts;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Modules\ModuleUnavailableException;

final class NullContactPushTarget implements ContactPushTarget {
    public function pushAllowed(): bool {
        return false;
    }

    public function push(Customer $customer, string $pluginId): string {
        throw ModuleUnavailableException::for('module.finance');
    }

    public function pushSupplier(Supplier $supplier, string $pluginId): string {
        throw ModuleUnavailableException::for('module.finance');
    }
}
