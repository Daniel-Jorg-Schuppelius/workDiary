<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SendDeadlineReminders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Privacy;

use App\Models\Organization;
use App\Models\Privacy\ComplianceFinding;
use App\Services\Privacy\{ComplianceAnalysisService, PrivacyDeadlineService};
use Illuminate\Console\Command;

/**
 * Erinnert an fristnahe/ueberfaellige Betroffenenanfragen (idempotent). Fuer den
 * Scheduler (stuendlich/taeglich) gedacht.
 */
class SendDeadlineReminders extends Command {
    protected $signature = 'privacy:deadlines';

    protected $description = 'Erinnert an fristnahe oder ueberfaellige Betroffenenanfragen (Art. 12).';

    public function handle(PrivacyDeadlineService $service, ComplianceAnalysisService $compliance): int {
        $requests = $service->remind();
        $incidents = $service->remindIncidents();

        // Fristen-Scan der Compliance-Lücken (Nachtrag 043b/c): hält ablaufende AVV/TOM-Nachweise aktuell — nur für
        // Orgs, die das Datenschutzmodul bereits nutzen (min. ein Katalog-/Befund-Datensatz), keine Zwangs-Materialisierung.
        $orgIds = ComplianceFinding::query()->withoutGlobalScopes()
            ->distinct()->pluck('organization_id')
            ->merge(\App\Models\Privacy\PrivacyRequirement::query()->withoutGlobalScopes()->distinct()->pluck('organization_id'))
            ->unique();
        $scanned = 0;
        foreach (Organization::query()->whereIn('id', $orgIds)->get() as $organization) {
            $compliance->run($organization);
            $scanned++;
        }

        $this->info("{$requests} Anfrage(n), {$incidents} Vorfall/Vorfaelle erinnert; Compliance-Scan für {$scanned} Organisation(en).");

        return self::SUCCESS;
    }
}
