<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallDetector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\TimeTargets;

use App\Enums\Project\ShortfallStatus;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Models\Time\TimeEntry;
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\Query\DateRange;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Vergleicht das Soll je Person und Tag mit der gebuchten Personenzeit des
 * Projekts (Feature 194, MVP-1468). Eine Unterschreitung über der Toleranz
 * legt einen Befund an oder öffnet einen erledigten wieder; erreicht die
 * Buchung das Soll, ist der Befund erledigt. Bestätigte Befunde bleiben
 * bestätigt, solange die Lücke besteht.
 */
final class ProjectTimeShortfallDetector {
    use AssertsStatusTransition;

    public const SETTING_TOLERANCE = 'project.target_tolerance_minutes';

    public const DEFAULT_TOLERANCE_MINUTES = 15;

    public function __construct(private readonly ProjectTimeTargetResolver $resolver) {}

    public static function toleranceMinutes(): int {
        return max(0, (int) Setting::get(self::SETTING_TOLERANCE, self::DEFAULT_TOLERANCE_MINUTES));
    }

    /**
     * @return array{created: int, reopened: int, resolved: int, unchanged: int, detected: Collection<int, ProjectTimeShortfall>}
     */
    public function checkRange(Project $project, CarbonImmutable $from, CarbonImmutable $to, ?int $toleranceMinutes = null): array {
        // Zwei Läufe (Nachtlauf, Hand-Aufruf) prüfen sonst denselben alten Stand und schreiben beide.
        return Cache::lock('project-time-shortfalls:' . $project->id, 120)
            ->block(15, fn (): array => $this->checkRangeLocked($project, $from, $to, $toleranceMinutes));
    }

    /**
     * @return array{created: int, reopened: int, resolved: int, unchanged: int, detected: Collection<int, ProjectTimeShortfall>}
     */
    private function checkRangeLocked(Project $project, CarbonImmutable $from, CarbonImmutable $to, ?int $toleranceMinutes): array {
        $tolerance = $toleranceMinutes ?? self::toleranceMinutes();
        $result = ['created' => 0, 'reopened' => 0, 'resolved' => 0, 'unchanged' => 0, 'detected' => new Collection];
        $userIds = $this->resolver->subjectUserIds($project);
        if ($userIds === []) {
            return $result;
        }
        $targets = $this->resolver->targetsFor($project, $userIds, $from, $to);
        $booked = $this->bookedMinutes($project, $userIds, $from, $to);
        $existing = $project->timeShortfalls()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', DateRange::days($from, $to))
            ->get()
            ->keyBy(static fn (ProjectTimeShortfall $s): string => $s->user_id . '|' . $s->date->toDateString());

        foreach ($targets as $userId => $days) {
            foreach ($days as $date => $target) {
                /** @var ProjectTimeShortfall|null $found */
                $found = $existing->get($userId . '|' . $date);
                $bookedMinutes = $booked[$userId][$date] ?? 0;
                $shortfall = $target - $bookedMinutes;
                if ($target <= 0 || $shortfall <= $tolerance) {
                    if ($found !== null && $found->status !== ShortfallStatus::Resolved) {
                        $this->assertStatusTransition($found->status, ShortfallStatus::Resolved);
                        $found->update(['status' => ShortfallStatus::Resolved, 'resolved_at' => now(), 'booked_minutes' => $bookedMinutes, 'shortfall_minutes' => 0]);
                        $result['resolved']++;
                    }
                    continue;
                }
                $values = [
                    'target_minutes' => $target,
                    'booked_minutes' => $bookedMinutes,
                    'shortfall_minutes' => $shortfall,
                    'tolerance_minutes' => $tolerance,
                ];
                if ($found === null) {
                    $created = $project->timeShortfalls()->create($values + [
                        'organization_id' => $project->organization_id,
                        'user_id' => $userId,
                        'date' => $date,
                        'status' => ShortfallStatus::Open,
                    ]);
                    $result['created']++;
                    $result['detected']->push($created);
                    continue;
                }
                if ($found->status === ShortfallStatus::Resolved) {
                    $this->assertStatusTransition($found->status, ShortfallStatus::Open);
                    $found->update($values + ['status' => ShortfallStatus::Open, 'resolved_at' => null, 'notified_at' => null]);
                    $result['reopened']++;
                    $result['detected']->push($found);
                    continue;
                }
                $found->update($values);
                $result['unchanged']++;
            }
        }

        return $result;
    }

    /**
     * Projekte, in denen die Person am Tag unter ihrem Soll liegt — live aus
     * Soll und Buchungen, ohne Befund zu schreiben (Tagesabschluss).
     *
     * @return list<array{project: Project, target: int, booked: int, missing: int}>
     */
    public function previewForUser(User $user, CarbonImmutable $day): array {
        $tolerance = self::toleranceMinutes();
        $out = [];
        $projects = Project::query()
            ->whereHas('timeTargets', static fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->orderBy('name')
            ->get();
        foreach ($projects as $project) {
            if (! in_array((int) $user->id, $this->resolver->subjectUserIds($project), true)) {
                continue;
            }
            $target = $this->resolver->targetFor($project, $user, $day);
            if ($target === null || $target <= 0) {
                continue;
            }
            $booked = $this->bookedMinutes($project, [(int) $user->id], $day, $day)[(int) $user->id][$day->toDateString()] ?? 0;
            if ($target - $booked > $tolerance) {
                $out[] = ['project' => $project, 'target' => $target, 'booked' => $booked, 'missing' => $target - $booked];
            }
        }

        return $out;
    }

    /**
     * Gebuchte Personenzeit je Person und Tag.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, int>>
     */
    private function bookedMinutes(Project $project, array $userIds, CarbonImmutable $from, CarbonImmutable $to): array {
        $rows = TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereIn('user_id', $userIds)
            ->personTime()
            ->whereBetween('date', DateRange::days($from, $to))
            ->groupBy('user_id', 'date')
            ->selectRaw('user_id, date, SUM(minutes) as minutes')
            ->get();
        $booked = [];
        foreach ($rows as $row) {
            $booked[(int) $row->user_id][CarbonImmutable::parse((string) $row->date)->toDateString()] = (int) $row->minutes;
        }

        return $booked;
    }
}
