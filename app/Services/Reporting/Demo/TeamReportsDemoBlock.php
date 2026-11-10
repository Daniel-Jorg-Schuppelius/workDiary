<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamReportsDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Demo;

use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Support\Tz;
use Carbon\CarbonImmutable;

/**
 * Team-Auswertungen zur Vorführung (MVP-1533): Ohne diesen Block hat die Demo
 * nur die drei Zeiten des Hauptauftrags an einem Tag, Wochen- und
 * Monatsberichte je Person bleiben meist leer. Er bucht vier Demo-Personen
 * über die letzten drei Wochen Arbeitszeit auf die Demo-Projekte. Den Reset
 * übernimmt der generische Demo-Reset (alle Zeiten der Organisation).
 */
final class TeamReportsDemoBlock implements DemoBlock {
    private const int DAYS_BACK = 21;

    /** Nutzerindex im Seed => [Grundminuten, Streuung, zwei Buchungen am Tag, abrechenbar, Tätigkeit]. */
    private const array PROFILES = [
        1 => [420, 60, true, true, TimeEntryActivityType::Project],
        2 => [390, 75, true, true, TimeEntryActivityType::Project],
        3 => [300, 45, false, false, TimeEntryActivityType::Internal],
        0 => [150, 40, false, false, TimeEntryActivityType::Admin],
    ];

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        if (! $context->moduleActive('module.auswertungen_team') || $context->projects->isEmpty()) {
            return ['team_time_entries' => 0];
        }
        $organization = $context->organization;
        $timezone = Tz::ofOrganization($organization);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $projects = $context->projects->values();

        $created = 0;
        for ($back = self::DAYS_BACK; $back >= 1; $back--) {
            $day = $today->subDays($back);
            if ($day->isWeekend()) {
                continue;
            }
            foreach (self::PROFILES as $userIndex => [$base, $spread, $split, $billable, $activity]) {
                $user = $context->users->get($userIndex);
                if ($user === null) {
                    continue;
                }
                $minutes = $base + (($back * 17 + $userIndex * 29) % $spread);
                $parts = $split ? [intdiv($minutes * 11, 20), $minutes - intdiv($minutes * 11, 20)] : [$minutes];
                $start = $day->setTime(8, 0);
                foreach ($parts as $part => $partMinutes) {
                    /** @var Project $project */
                    $project = $projects[($back + $userIndex + $part) % $projects->count()];
                    TimeEntry::query()->create([
                        'organization_id' => $organization->id,
                        'project_id' => $project->id,
                        'user_id' => $user->id,
                        'date' => $day->toDateString(),
                        'started_at' => $start->utc(),
                        'ended_at' => $start->addMinutes($partMinutes)->utc(),
                        'minutes' => $partMinutes,
                        'kind' => TimeEntryKind::Work,
                        'activity_type' => $activity,
                        'description' => (string) __('demo.team.description', ['project' => $project->name]),
                        'billable' => $billable,
                    ]);
                    $created++;
                    // 30 Minuten Pause zwischen Vormittag und Nachmittag.
                    $start = $start->addMinutes($partMinutes + 30);
                }
            }
        }

        return ['team_time_entries' => $created];
    }

    public function purge(Organization $organization): void {}
}
