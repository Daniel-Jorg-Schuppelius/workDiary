<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullFreeSlotSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Contracts;

use App\Models\Platform\User;
use Carbon\CarbonImmutable;

final class NullFreeSlotSource implements FreeSlotSource {
    public function freeSlots(User $user, CarbonImmutable $date): array {
        return [];
    }
}
