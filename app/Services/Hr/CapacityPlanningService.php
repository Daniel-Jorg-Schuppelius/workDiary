<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapacityPlanningService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Applications\JobRequisition;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, Team, User};
use App\Services\Flextime\FlexCalculator;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Personal-Kapazitätsplanung (MVP-940): Kapazität je Team und Woche aus der
 * Sollzeit der Mitglieder (Feiertage und genehmigter Urlaub abgezogen, über
 * {@see FlexCalculator::targetMinutes()}) gegen den geplanten Bedarf der
 * zugewiesenen Aufträge; offene Stellen je Team als Hinweis.
 */
final class CapacityPlanningService {
    public const DEFAULT_WEEKS = 6;

    public function __construct(private readonly FlexCalculator $flex) {}

    /**
     * @return list<array{team: ?Team, members: int, weeks: list<array{start: CarbonImmutable, capacity: int, demand: int, utilization: ?int}>}>
     */
    public function plan(Organization $organization, int $weeks = self::DEFAULT_WEEKS): array {
        $start = CarbonImmutable::now()->startOfWeek();
        $teams = Team::query()->where('organization_id', $organization->id)->with(['members' => fn ($q) => $q->whereNull('deactivated_at')])->orderBy('name')->get();
        $rows = [];
        foreach ($teams as $team) {
            $rows[] = $this->row($team, $team->members, $start, $weeks);
        }

        return $rows;
    }

    /** Offene Stellen der Organisation (Summe der gesuchten Personen), als Hinweis zur Kapazität. */
    public function openHeadcount(Organization $organization): int {
        return (int) JobRequisition::query()->where('organization_id', $organization->id)->where('status', 'open')->sum('headcount');
    }

    /**
     * @param Collection<int, User> $members
     * @return array{team: ?Team, members: int, weeks: list<array{start: CarbonImmutable, capacity: int, demand: int, utilization: ?int}>}
     */
    private function row(?Team $team, Collection $members, CarbonImmutable $start, int $weeks): array {
        $ids = $members->modelKeys();
        $result = [];
        for ($w = 0; $w < $weeks; $w++) {
            $from = $start->addWeeks($w);
            $to = $from->addDays(6);
            $capacity = 0;
            foreach ($members as $member) {
                for ($d = 0; $d < 7; $d++) {
                    $capacity += $this->flex->targetMinutes($member, $from->addDays($d));
                }
            }
            $demand = 0;
            if ($ids !== []) {
                $query = DiaryEntry::query()->whereIn('assigned_user_id', $ids);
                DateRange::whereTimestampBetween($query, 'start_at', $from, $to);
                $demand = (int) $query->sum('planned_minutes');
            }
            $result[] = ['start' => $from, 'capacity' => $capacity, 'demand' => $demand, 'utilization' => $capacity > 0 ? (int) round($demand / $capacity * 100) : null];
        }

        return [
            'team' => $team,
            'members' => count($ids),
            'weeks' => $result,
        ];
    }
}
