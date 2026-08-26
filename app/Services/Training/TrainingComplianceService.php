<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingComplianceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\Training\TrainingAssignmentState;
use App\Enums\User\UserRole;
use App\Models\Training\{TrainingAssignment, TrainingCourse};
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Erfüllungsgrad des Trainingsmanagements (Feature 145, Auswertung nach
 * Feature 002): Soll-Einträge zum Stichtag je Team, je Rolle und je Kurs.
 *
 * „Erfüllt" heißt hier: nicht überfällig — entweder liegt ein gültiger
 * Nachweis vor oder der Termin ist noch nicht erreicht. Genau das fragt
 * ein Audit nach ISO 45001 7.2 ab.
 *
 * @phpstan-type TrainingGroupCounts array{label: string, total: int, fulfilled: int, due: int, overdue: int}
 * @phpstan-type TrainingGroupRow array{label: string, total: int, fulfilled: int, due: int, overdue: int, key: string, rate: float}
 * @phpstan-type TrainingComplianceRow array{user: string, course: string, due_at: string|null, fulfilled_at: string|null, state: string, proof: string}
 * @phpstan-type TrainingComplianceReport array{totals: array{assignments: int, fulfilled: int, due: int, overdue: int, rate: float}, byTeam: list<TrainingGroupRow>, byRole: list<TrainingGroupRow>, byCourse: list<TrainingGroupRow>, rows: list<TrainingComplianceRow>}
 */
class TrainingComplianceService {
    /**
     * @param  Builder<TrainingAssignment>  $query  bereits gefilterte Soll-Einträge
     * @return TrainingComplianceReport
     */
    public function build(Builder $query, ?Carbon $today = null): array {
        $today = ($today ?? Carbon::today())->startOfDay();

        /** @var \Illuminate\Database\Eloquent\Collection<int, TrainingAssignment> $assignments */
        $assignments = $query
            ->with(['user:id,name', 'course:id,title', 'instruction:id,instruction_no,topic,held_on'])
            ->get();

        $userIds = array_values($assignments->pluck('user_id')->map(static fn($id): int => (int) $id)->unique()->all());
        $teamsByUser = $this->teamsByUser($userIds);
        $rolesByUser = $this->rolesByUser($userIds);

        $totals = ['assignments' => 0, 'fulfilled' => 0, 'due' => 0, 'overdue' => 0];
        /** @var array<string, array{label: string, total: int, fulfilled: int, due: int, overdue: int}> $byTeam */
        $byTeam = [];
        /** @var array<string, array{label: string, total: int, fulfilled: int, due: int, overdue: int}> $byRole */
        $byRole = [];
        /** @var array<string, array{label: string, total: int, fulfilled: int, due: int, overdue: int}> $byCourse */
        $byCourse = [];
        $rows = [];

        foreach ($assignments as $assignment) {
            $state = $assignment->state($today);
            /** @var 'fulfilled'|'due'|'overdue' $bucket */
            $bucket = match ($state) {
                TrainingAssignmentState::Overdue => 'overdue',
                TrainingAssignmentState::Due => 'due',
                default => 'fulfilled',
            };

            $totals['assignments']++;
            $totals[$bucket]++;

            $userId = (int) $assignment->user_id;
            $courseKey = (string) $assignment->training_course_id;
            $courseModel = $assignment->course;
            $courseLabel = $courseModel instanceof TrainingCourse ? $courseModel->title : '—';
            $byCourse = $this->add($byCourse, $courseKey, $courseLabel, $bucket);

            $teams = $teamsByUser[$userId] ?? [];
            if ($teams === []) {
                $byTeam = $this->add($byTeam, '-', (string) __('training.report.no_team'), $bucket);
            }
            foreach ($teams as $teamId => $teamName) {
                $byTeam = $this->add($byTeam, (string) $teamId, $teamName, $bucket);
            }

            $roles = $rolesByUser[$userId] ?? [];
            if ($roles === []) {
                $byRole = $this->add($byRole, '-', (string) __('training.report.no_role'), $bucket);
            }
            foreach ($roles as $roleSlug) {
                $role = UserRole::tryFrom($roleSlug);
                $byRole = $this->add($byRole, $roleSlug, $role instanceof UserRole ? $role->label() : $roleSlug, $bucket);
            }

            $rows[] = [
                'user' => $assignment->user instanceof User ? $assignment->user->name : '—',
                'course' => $courseLabel,
                'due_at' => $assignment->due_at?->toDateString(),
                'fulfilled_at' => $assignment->fulfilled_at?->toDateString(),
                'state' => $state->value,
                'proof' => $assignment->instruction !== null
                    ? $assignment->instruction->displayNo() . ' — ' . $assignment->instruction->topic
                    : '',
            ];
        }

        return [
            'totals' => $totals + ['rate' => $this->rate($totals['assignments'], $totals['overdue'])],
            'byTeam' => $this->finalize($byTeam),
            'byRole' => $this->finalize($byRole),
            'byCourse' => $this->finalize($byCourse),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, TrainingGroupCounts>  $target
     * @param  'fulfilled'|'due'|'overdue'  $bucket
     * @return array<string, TrainingGroupCounts>
     */
    private function add(array $target, string $key, string $label, string $bucket): array {
        $group = $target[$key] ?? ['label' => $label, 'total' => 0, 'fulfilled' => 0, 'due' => 0, 'overdue' => 0];
        $group['total']++;
        // Fester Zähler statt dynamischem Schlüssel — hält die Shape typisiert.
        if ($bucket === 'overdue') {
            $group['overdue']++;
        } elseif ($bucket === 'due') {
            $group['due']++;
        } else {
            $group['fulfilled']++;
        }
        $target[$key] = $group;

        return $target;
    }

    /**
     * @param  array<string, TrainingGroupCounts>  $groups
     * @return list<TrainingGroupRow>
     */
    private function finalize(array $groups): array {
        $result = [];
        foreach ($groups as $key => $group) {
            $result[] = $group + ['key' => $key, 'rate' => $this->rate($group['total'], $group['overdue'])];
        }

        usort($result, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['label'], $b['label']));

        return $result;
    }

    private function rate(int $total, int $overdue): float {
        return $total < 1 ? 100.0 : round((($total - $overdue) / $total) * 100, 1);
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array<int, string>>
     */
    private function teamsByUser(array $userIds): array {
        if ($userIds === []) {
            return [];
        }

        $map = [];
        $rows = DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->whereIn('team_user.user_id', $userIds)
            ->get(['team_user.user_id', 'teams.id as team_id', 'teams.name']);
        foreach ($rows as $row) {
            $map[(int) $row->user_id][(int) $row->team_id] = (string) $row->name;
        }

        return $map;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, list<string>>
     */
    private function rolesByUser(array $userIds): array {
        if ($userIds === []) {
            return [];
        }

        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        $map = [];
        $rows = DB::table($pivotTable)
            ->join($rolesTable, $rolesTable . '.id', '=', $pivotTable . '.role_id')
            ->where($pivotTable . '.model_type', User::class)
            ->whereIn($pivotTable . '.' . $morphKey, $userIds)
            ->get([$pivotTable . '.' . $morphKey . ' as user_id', $rolesTable . '.name']);
        foreach ($rows as $row) {
            $map[(int) $row->user_id][] = (string) $row->name;
        }

        foreach ($map as $userId => $roles) {
            $map[$userId] = array_values(array_unique($roles));
        }

        return $map;
    }
}
