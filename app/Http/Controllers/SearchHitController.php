<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchHitController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Search\SearchSourceType;
use App\Models\{Project, TimeEntry, Timesheet};
use App\Services\UI\DateRangeContext;
use App\Support\{CarbonFmt, Sqid};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Sprung aus der Tätigkeitsrecherche (Feature 153) auf Zeiten und
 * Stundenzettel. Beide haben keine eigene Detailseite; ihre Projekt-Reiter
 * folgen dem globalen Header-Zeitraum (AGENTS.md §8) — ein Eintrag aus einem
 * anderen Monat stünde dort nicht. Der Sprung setzt den Zeitraum deshalb auf
 * den Tag des Eintrags (wie der Zeitraum-Umschalter im Header) und sagt es an.
 */
class SearchHitController extends Controller {
    public function open(DateRangeContext $range, string $type, string $id): RedirectResponse {
        if ($type === SearchSourceType::Timesheet->value) {
            $timesheet = Timesheet::query()->findOrFail(Sqid::decodeOrAbort(Timesheet::class, $id));
            Gate::authorize('view', $timesheet);

            return $this->jump($range, $timesheet->work_date, $timesheet->project_id, 'timesheets', 'timesheet-' . $timesheet->sqid);
        }

        $entry = TimeEntry::query()->findOrFail(Sqid::decodeOrAbort(TimeEntry::class, $id));
        Gate::authorize('view', $entry);

        return $this->jump($range, $entry->date, $entry->project_id, 'time', 'time-entry-' . $entry->sqid);
    }

    private function jump(DateRangeContext $range, ?CarbonInterface $date, ?int $projectId, string $tab, string $anchor): RedirectResponse {
        abort_if($date === null || $projectId === null, 404);

        $project = Project::query()->findOrFail($projectId);
        Gate::authorize('view', $project);

        $day = CarbonImmutable::instance($date)->toDateString();
        $range->set(DateRangeContext::PRESET_CUSTOM, $day, $day);

        return redirect()
            ->to(route('projects.show', ['project' => $project, 'tab' => $tab]) . '#' . $anchor)
            ->with('info', __('search.open.range_set', ['date' => CarbonFmt::fdate($date)]));
    }
}
