<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolveLoginNetworksCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Enums\Auth\LoginPolicyMode;
use App\Models\Auth\OrganizationLoginPolicy;
use App\Services\Auth\LoginPolicy\LoginNetworks;
use Illuminate\Console\Command;

/**
 * Löst die DNS-Namen der erlaubten Netze aller Organisationen mit aktiver
 * Netzregel auf (Feature 205, MVP-1655; Scheduler alle fünf Minuten). Die
 * Anmeldung liest nur das Ergebnis im Cache und wartet nie auf DNS.
 */
class ResolveLoginNetworksCommand extends Command {
    protected $signature = 'security:resolve-login-networks';

    protected $description = 'Löst die DNS-Namen der Büronetze aus den Anmelderichtlinien auf (Cache für die Anmeldung).';

    public function handle(LoginNetworks $networks): int {
        $resolved = 0;

        OrganizationLoginPolicy::query()
            ->withoutGlobalScopes()
            ->where('network_mode', '!=', LoginPolicyMode::Off->value)
            ->whereNotNull('networks')
            ->orderBy('id')
            ->each(function (OrganizationLoginPolicy $policy) use ($networks, &$resolved): void {
                $hosts = $networks->refresh((int) $policy->organization_id, $policy->networkEntries());
                $resolved += count($hosts);
            });

        $this->info((string) __('login_policy.command.resolved', ['count' => $resolved]));

        return self::SUCCESS;
    }
}
