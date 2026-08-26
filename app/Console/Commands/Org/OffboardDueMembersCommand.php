<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OffboardDueMembersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Org;

use App\Services\Org\UserOffboardingService;
use Illuminate\Console\Command;

/**
 * Vollzieht vorgemerkte Mitarbeiter-Austritte am Stichtag (Feature 126,
 * MVP-689): deaktiviert das Konto, beendet Sitzungen, widerruft API-Tokens.
 */
class OffboardDueMembersCommand extends Command {
    protected $signature = 'org:offboard-due';

    protected $description = 'Vollzieht fällige Mitarbeiter-Austritte (left_at erreicht): Konto deaktivieren, Sitzungen/Tokens beenden.';

    public function handle(UserOffboardingService $offboarding): int {
        $count = $offboarding->runDue();
        $this->info("{$count} Austritt(e) vollzogen.");

        return self::SUCCESS;
    }
}
