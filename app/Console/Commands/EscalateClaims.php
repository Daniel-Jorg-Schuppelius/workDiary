<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EscalateClaims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Claims\ClaimCaseService;
use Illuminate\Console\Command;

/**
 * Fristeneskalation für Reklamationen (Feature 072, MVP-255): meldet
 * überfällige offene Fälle an die Verantwortlichen — einmal je Fall und
 * Tag (Nachweis über notification_dispatch_log). Registriert in
 * config/scheduler.php (claims.escalate), nicht hartcodiert.
 */
class EscalateClaims extends Command {
    protected $signature = 'claims:escalate';

    protected $description = 'Überfällige Reklamationen an Verantwortliche eskalieren';

    public function handle(ClaimCaseService $service): int {
        $total = 0;
        foreach (Organization::query()->cursor() as $organization) {
            $total += $service->escalateOverdue($organization);
        }
        $this->info("Eskalationen versendet: {$total}");

        return self::SUCCESS;
    }
}
