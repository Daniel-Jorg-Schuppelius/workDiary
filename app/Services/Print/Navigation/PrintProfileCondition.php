<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintProfileCondition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print\Navigation;

use App\Models\Platform\{Organization, User};
use App\Services\Navigation\Contracts\NavigationCondition;
use App\Services\Print\PrintOrderService;

/** Druckaufträge (MVP-459) nur mit installiertem Branchenprofil druck-kopiershop. */
final class PrintProfileCondition implements NavigationCondition {
    public function __construct(private readonly PrintOrderService $orders) {}

    public function key(): string {
        return 'print.profile';
    }

    public function passes(?User $user, ?Organization $organization): bool {
        return $organization !== null && $this->orders->isPrintProfileActive($organization);
    }
}
