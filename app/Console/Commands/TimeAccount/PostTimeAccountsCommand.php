<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostTimeAccountsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\TimeAccount;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use App\Services\TimeAccount\TimeAccountPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Bebucht die konfigurierten Zeitkonten aus dem Bestand (MVP-526) —
 * idempotent über ein Rückblick-Fenster; inklusive Kappungsbuchungen
 * beim Monatsabschluss. Für den täglichen Scheduler-Lauf gedacht.
 */
class PostTimeAccountsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'accounts:post
        {--days=40 : Rückblick-Fenster in Tagen}';

    protected $description = 'Bebucht konfigurierte Zeitkonten aus Zeitregel-Ergebnissen, Anwesenheiten, Abwesenheiten, Dienst-Zählern und externen Positionen (idempotent, append-only).';

    public function handle(TimeAccountPostingService $service): int {
        $days = max(1, (int) $this->option('days'));
        $to = CarbonImmutable::now();
        $from = $to->subDays($days)->startOfDay();

        $totals = ['posted' => 0, 'skipped' => 0, 'capped' => 0];

        $organizationIds = Organization::query()->orderBy('id')->pluck('id');
        foreach ($organizationIds as $organizationId) {
            $organization = Organization::query()->whereKey($organizationId)->first();
            if ($organization === null) {
                continue;
            }
            $this->withOrganizationContext($organization, function (Organization $organization) use ($service, $from, $to, &$totals): void {
                $stats = $service->postRange($organization, $from, $to);
                foreach ($stats as $k => $v) {
                    $totals[$k] += $v;
                }
            });
        }

        $this->info(sprintf(
            'Zeitkonten-Lauf: %d Buchungen, %d Slots übersprungen, %d Kappungen.',
            $totals['posted'],
            $totals['skipped'],
            $totals['capped'],
        ));

        return self::SUCCESS;
    }
}
