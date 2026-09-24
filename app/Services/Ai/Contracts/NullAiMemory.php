<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullAiMemory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Platform\Organization;

final class NullAiMemory implements AiMemory {
    public function seedDefaults(Organization $organization, ?int $userId = null): int {
        return 0;
    }

    public function deleteForCustomer(Organization $organization, int $customerId): int {
        return 0;
    }
}
