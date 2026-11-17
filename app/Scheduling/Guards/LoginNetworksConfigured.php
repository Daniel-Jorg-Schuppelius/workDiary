<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginNetworksConfigured.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Enums\Auth\LoginPolicyMode;
use App\Models\Auth\OrganizationLoginPolicy;
use App\Scheduling\Contracts\JobGuard;

/** Büronetze auflösen (security:resolve-login-networks): nur, wenn eine Organisation eine Netzregel führt. */
final class LoginNetworksConfigured implements JobGuard {
    public function shouldRun(): bool {
        return OrganizationLoginPolicy::query()
            ->withoutGlobalScopes()
            ->where('network_mode', '!=', LoginPolicyMode::Off->value)
            ->whereNotNull('networks')
            ->exists();
    }
}
