<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RollShiftRotationsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Schedule;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use App\Services\Schedule\ShiftRotationRoller;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Schreibt aktive Rollpläne als Draft-Dienste fort (MVP-522). Für den
 * Scheduler gedacht (täglich); manuell mit --weeks für ein größeres Fenster.
 */
class RollShiftRotationsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'shifts:roll-forward
        {--weeks=4 : Planungsfenster in Wochen ab heute}';

    protected $description = 'Erzeugt aus aktiven Rollplan-Zuweisungen Draft-Dienste (idempotent; manuelle Planung und Abwesenheiten gewinnen).';

    public function handle(ShiftRotationRoller $roller): int {
        $weeks = max(1, (int) $this->option('weeks'));
        $from = CarbonImmutable::now()->startOfDay();

        $totals = ['created' => 0, 'skipped' => 0];

        $organizationIds = Organization::query()->orderBy('id')->pluck('id');
        foreach ($organizationIds as $organizationId) {
            $organization = Organization::query()->whereKey($organizationId)->first();
            if ($organization === null) {
                continue;
            }
            $this->withOrganizationContext($organization, function (Organization $organization) use ($roller, $from, $weeks, &$totals): void {
                $stats = $roller->rollForward($organization, $from, $weeks);
                $totals['created'] += $stats['created'];
                $totals['skipped'] += $stats['skipped'];
            });
        }

        $this->info(sprintf('Rollplan-Fortschreibung: %d Dienste erzeugt, %d Slots übersprungen.', $totals['created'], $totals['skipped']));

        return self::SUCCESS;
    }
}
