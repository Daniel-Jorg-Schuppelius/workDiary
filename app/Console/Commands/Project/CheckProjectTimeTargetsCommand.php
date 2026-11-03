<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckProjectTimeTargetsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Project;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Services\Project\TimeTargets\{ProjectTimeShortfallDetector, ProjectTimeShortfallNotifier};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Nächtlicher Abgleich der Projekt-Sollzeiten (Feature 194, MVP-1468): prüft
 * den Vortag und die Tage davor erneut, damit Nachbuchungen Befunde erledigen.
 */
final class CheckProjectTimeTargetsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'projects:check-time-targets'
        . ' {--days=7 : Nachlauf in Tagen bis einschließlich des Prüftags}'
        . ' {--date= : Prüftag (Y-m-d) in der Zeitzone der Organisation, sonst gestern} '
        . self::ORGANIZATION_OPTION;

    protected $description = 'Prüft die Projekt-Sollzeiten je Person gegen die gebuchte Zeit und führt die Tagesbefunde nach (Feature 194)';

    public function handle(ProjectTimeShortfallDetector $detector, ProjectTimeShortfallNotifier $notifier): int {
        $days = max(1, (int) $this->option('days'));
        $dateOption = $this->option('date');

        $failures = $this->forEachOrganization(function (Organization $organization) use ($detector, $notifier, $days, $dateOption): void {
            $tz = Tz::ofOrganization($organization);
            $to = is_string($dateOption) && $dateOption !== ''
                ? CarbonImmutable::parse($dateOption, $tz)->startOfDay()
                : CarbonImmutable::now($tz)->subDay()->startOfDay();
            $from = $to->subDays($days - 1);

            $totals = ['created' => 0, 'reopened' => 0, 'resolved' => 0, 'unchanged' => 0];
            $projects = Project::query()->whereHas('timeTargets')->orderBy('id')->get();
            foreach ($projects as $project) {
                $result = $detector->checkRange($project, $from, $to);
                foreach (array_keys($totals) as $key) {
                    $totals[$key] += $result[$key];
                }
                if ($result['detected']->isNotEmpty()) {
                    $sent = $notifier->notify($project, $result['detected']);
                    $this->line(sprintf('  %s: %d neu unter Soll, %d Meldungen', $project->name, $result['detected']->count(), $sent));
                }
            }
            $this->line(sprintf(
                'Organisation #%d (%s): %s bis %s, %d Projekte — neu %d, wieder offen %d, erledigt %d, unverändert %d',
                $organization->id,
                $organization->name,
                $from->toDateString(),
                $to->toDateString(),
                $projects->count(),
                $totals['created'],
                $totals['reopened'],
                $totals['resolved'],
                $totals['unchanged'],
            ));
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
