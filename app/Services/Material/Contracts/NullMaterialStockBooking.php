<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullMaterialStockBooking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Material\Contracts;

use App\Models\Material\MaterialUsage;
use App\Models\Platform\User;

final class NullMaterialStockBooking implements MaterialStockBooking {
    public function sync(MaterialUsage $usage, User $actor, bool $allowNegative = false): void {}

    public function release(MaterialUsage $usage, ?User $actor): void {}
}
