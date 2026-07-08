<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdvisoriesPullCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Services\Security\OsvAdvisoryService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Zieht Sicherheitshinweise für die installierten Abhängigkeiten aus der
 * OSV-Datenbank (Rang 70). Täglich per Scheduler; manuell über die
 * Admin-Sicherheitsseite.
 */
class AdvisoriesPullCommand extends Command {
    protected $signature = 'security:advisories-pull';

    protected $description = 'Sicherheitshinweise (OSV) für composer.lock/package-lock.json aktualisieren';

    public function handle(OsvAdvisoryService $service): int {
        try {
            $result = $service->pull();
        } catch (Throwable $e) {
            $this->error('Advisory-Pull fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d Pakete geprüft — %d offene Advisories (%d neu, %d als behoben markiert).',
            $result['checked'],
            $result['open'],
            $result['new'],
            $result['resolved'],
        ));

        return self::SUCCESS;
    }
}
