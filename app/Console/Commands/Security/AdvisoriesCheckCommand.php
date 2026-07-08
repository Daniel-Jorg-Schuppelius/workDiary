<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdvisoriesCheckCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Models\SecurityAdvisory;
use Illuminate\Console\Command;

/**
 * Gate-Anschluss (Rang 70): Exit 1, wenn offene Advisories mit Schweregrad
 * high/critical vorliegen — als Warn-Step in `scripts/security-gate.sh`
 * (nicht blockierend, da die CI-Datenbank keine Advisory-Daten hat).
 */
class AdvisoriesCheckCommand extends Command {
    protected $signature = 'security:advisories-check';

    protected $description = 'Prüft auf offene high/critical-Sicherheitshinweise (Exit 1 bei Treffern)';

    public function handle(): int {
        $open = SecurityAdvisory::openHighOrCritical();
        if ($open > 0) {
            $this->error(sprintf(
                '%d offene high/critical-Advisories — Details: security:advisories-pull bzw. Admin → Sicherheit.',
                $open,
            ));

            return self::FAILURE;
        }

        $this->info('Keine offenen high/critical-Advisories.');

        return self::SUCCESS;
    }
}
