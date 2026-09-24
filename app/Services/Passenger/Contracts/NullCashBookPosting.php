<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullCashBookPosting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Passenger\Contracts;

use App\Models\Finance\{CashEntry, CashRegister};
use App\Modules\ModuleUnavailableException;

final class NullCashBookPosting implements CashBookPosting {
    public function record(CashRegister $register, array $data): CashEntry {
        throw ModuleUnavailableException::for('module.kasse');
    }
}
