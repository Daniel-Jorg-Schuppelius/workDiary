<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Diary\{Priority, Status as DiaryStatus};
use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Enums\Tour\TourStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{DiaryEntry, EntryType, Task, Tour, User};
use Carbon\{Carbon, CarbonImmutable};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Operations-Auswertung: Service-Aufträge (DiaryEntries vom EntryType
 * "service"), Tasks und Touren im Zeitraum.
 */
class OperationsReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->globalDateRangeBounds();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $orders = $this->aggregateOrders($fromDate, $toDate, $scope, $userId);
        $tasks = $this->aggregateTasks($fromDate, $toDate, $scope, $userId);
        $tours = $this->aggregateTours($fromDate, $toDate, $scope, $userId);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($orders, $tasks, $tours, $from, $to, $scope, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($orders, $tasks, $tours, $from, $to, $scope, $request);
        }

        return view('reports.operations', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'orders' => $orders,
            'tasks' => $tasks,
            'tours' => $tours,
        ]);
    }
    /**
     * @return array{
     *   total:int,
     *   service_minutes:int,
     *   by_status: array<string, int>,
     *   by_priority: array<string, int>,
     *   completion_rate: float|null
     * }
     */
    private function aggregateOrders(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId): array {
        $q = DiaryEntry::query()
            ->whereHas('entryType', fn($t) => $t->where('slug', EntryType::SLUG_SERVICE))
            ->whereBetween('scheduled_for', [$from, $to]);
        if ($scope === 'mine') {
            $q->where('assigned_user_id', $userId);
        }
        /** @var Collection<int, DiaryEntry> $rows */
        $rows = $q->get(['status', 'priority', 'service_minutes']);

        $statusMap = [
            DiaryStatus::Planned->value => 'open',
            DiaryStatus::Accepted->value => 'open',
            DiaryStatus::InProgress->value => 'in_progress',
            DiaryStatus::WaitingCustomer->value => 'problem',
            DiaryStatus::WaitingMaterial->value => 'problem',
            DiaryStatus::Completed->value => 'done',
            DiaryStatus::AcceptedFinal->value => 'done',
            DiaryStatus::Invoiced->value => 'done',
        ];
        $byStatus = ['open' => 0, 'in_progress' => 0, 'problem' => 0, 'done' => 0];
        /** @var array<string, int> $byPriority */
        $byPriority = array_fill_keys(Priority::values(), 0);
        $minutes = 0;
        $cancelled = 0;
        foreach ($rows as $r) {
            $label = $statusMap[$r->status->value] ?? null;
            if ($label === null) {
                $cancelled++; // Stornierte Aufträge zählen nicht in die Statusverteilung.
            } else {
                $byStatus[$label]++;
            }
            if ($r->priority !== null) {
                $byPriority[$r->priority->value]++;
            }
            $minutes += (int) $r->service_minutes;
        }
        $total = $rows->count();
        // "problem" zählt nicht als abgeschlossen, wirkt aber nicht als Erfolg.
        $done = $byStatus['done'];
        $relevant = $total - $cancelled; // Stornierte Aufträge fließen nicht in die Abschlussquote ein.

        return [
            'total' => $total,
            'service_minutes' => $minutes,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'completion_rate' => $relevant > 0 ? $done / $relevant : null,
        ];
    }

    /**
     * @return array{
     *   total:int,
     *   by_status: array<string, int>,
     *   by_priority: array<string, int>,
     *   overdue:int,
     *   completion_rate: float|null
     * }
     */
    private function aggregateTasks(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId): array {
        // Tasks: aufgenommen werden Tasks, die im Zeitraum erstellt
        // oder fällig sind oder zuletzt aktualisiert wurden.
        $q = Task::query()
            ->where(function ($w) use ($from, $to): void {
                $w->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('updated_at', [$from, $to])
                    ->orWhereBetween('due_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->whereNull('archived_at');
        if ($scope === 'mine') {
            $q->where(function ($w) use ($userId): void {
                $w->where('assigned_to', $userId)->orWhere('created_by', $userId);
            });
        }
        /** @var Collection<int, Task> $rows */
        $rows = $q->get(['status', 'priority', 'due_date']);

        /** @var array<string, int> $byStatus */
        $byStatus = array_fill_keys(TaskStatus::values(), 0);
        /** @var array<string, int> $byPriority */
        $byPriority = array_fill_keys(TaskPriority::values(), 0);
        $overdue = 0;
        $today = Carbon::today();
        foreach ($rows as $r) {
            $statusValue = $r->status->value;
            $priorityValue = $r->priority->value;
            $byStatus[$statusValue] = ($byStatus[$statusValue] ?? 0) + 1;
            $byPriority[$priorityValue] = ($byPriority[$priorityValue] ?? 0) + 1;
            $dueRaw = $r->getAttribute('due_date');
            if ($r->status !== TaskStatus::Done && $dueRaw !== null) {
                $due = $dueRaw instanceof \DateTimeInterface ? Carbon::instance($dueRaw) : Carbon::parse((string) $dueRaw);
                if ($due->lt($today)) {
                    $overdue++;
                }
            }
        }
        $total = $rows->count();
        $done = $byStatus[TaskStatus::Done->value] ?? 0;

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'overdue' => $overdue,
            'completion_rate' => $total > 0 ? (float) $done / $total : null,
        ];
    }

    /**
     * @return array{
     *   total:int,
     *   completed:int,
     *   planned_distance_km: float,
     *   planned_minutes: int,
     *   per_user: array<int, array{user: User, count:int, distance_km:float, minutes:int}>
     * }
     */
    private function aggregateTours(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId): array {
        $q = Tour::query()->whereBetween('tour_date', [$from->toDateString(), $to->toDateString()]);
        if ($scope === 'mine') {
            $q->where('user_id', $userId);
        }
        /** @var Collection<int, Tour> $rows */
        $rows = $q->get(['user_id', 'planned_distance_km', 'planned_duration_minutes', 'status']);

        $total = $rows->count();
        $completed = 0;
        $distance = 0.0;
        $minutes = 0;
        /** @var array<int, array{count:int, distance_km:float, minutes:int}> $byUser */
        $byUser = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = ['count' => 0, 'distance_km' => 0.0, 'minutes' => 0];
            }
            $byUser[$uid]['count']++;
            $byUser[$uid]['distance_km'] += (float) $r->planned_distance_km;
            $byUser[$uid]['minutes'] += (int) $r->planned_duration_minutes;
            $distance += (float) $r->planned_distance_km;
            $minutes += (int) $r->planned_duration_minutes;
            if ($r->status === TourStatus::Completed) {
                $completed++;
            }
        }

        $perUser = [];
        if ($byUser !== []) {
            /** @var Collection<int, User> $users */
            $users = User::query()->whereIn('id', array_keys($byUser))->orderBy('name')->get();
            foreach ($users as $u) {
                $uid = (int) $u->id;
                $perUser[$uid] = [
                    'user' => $u,
                    'count' => $byUser[$uid]['count'],
                    'distance_km' => $byUser[$uid]['distance_km'],
                    'minutes' => $byUser[$uid]['minutes'],
                ];
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'planned_distance_km' => $distance,
            'planned_minutes' => $minutes,
            'per_user' => $perUser,
        ];
    }

    /**
     * @param  array{total:int, service_minutes:int, by_status: array<string,int>, by_priority: array<string,int>, completion_rate: float|null}  $orders
     * @param  array{total:int, by_status: array<string,int>, by_priority: array<string,int>, overdue:int, completion_rate: float|null}  $tasks
     * @param  array{total:int, completed:int, planned_distance_km:float, planned_minutes:int, per_user: array<int, array{user: User, count:int, distance_km:float, minutes:int}>}  $tours
     */
    private function exportCsv(array $orders, array $tasks, array $tours, string $from, string $to, string $scope, Request $request): Response {
        $filename = sprintf('operations_%s_%s.csv', $from, $to);
        $rows = [];
        $rows[] = ['Bereich', 'Kennzahl', 'Wert'];
        $rows[] = ['Service-Aufträge', 'Gesamt', $orders['total']];
        $rows[] = ['Service-Aufträge', 'Servicezeit (min)', $orders['service_minutes']];
        foreach ($orders['by_status'] as $st => $c) {
            $rows[] = ['Service-Aufträge', 'Status: ' . $st, $c];
        }
        foreach ($orders['by_priority'] as $p => $c) {
            $rows[] = ['Service-Aufträge', 'Priorität: ' . $p, $c];
        }
        $rows[] = ['Service-Aufträge', 'Abschlussquote %', $orders['completion_rate'] !== null ? NumberHelper::toUSFormat($orders['completion_rate'] * 100, 1) : ''];

        $rows[] = ['Tasks', 'Gesamt', $tasks['total']];
        $rows[] = ['Tasks', 'Überfällig', $tasks['overdue']];
        foreach ($tasks['by_status'] as $st => $c) {
            $rows[] = ['Tasks', 'Status: ' . $st, $c];
        }
        foreach ($tasks['by_priority'] as $p => $c) {
            $rows[] = ['Tasks', 'Priorität: ' . $p, $c];
        }
        $rows[] = ['Tasks', 'Abschlussquote %', $tasks['completion_rate'] !== null ? NumberHelper::toUSFormat($tasks['completion_rate'] * 100, 1) : ''];

        $rows[] = ['Touren', 'Gesamt', $tours['total']];
        $rows[] = ['Touren', 'Abgeschlossen', $tours['completed']];
        $rows[] = ['Touren', 'Plan-km Σ', NumberHelper::toUSFormat($tours['planned_distance_km'], 2)];
        $rows[] = ['Touren', 'Plan-Minuten Σ', $tours['planned_minutes']];
        foreach ($tours['per_user'] as $u) {
            $rows[] = [
                'Touren',
                'User: ' . $u['user']->name . ' (km / Min / Anz)',
                sprintf('%s / %d / %d', NumberHelper::toUSFormat($u['distance_km'], 2), $u['minutes'], $u['count']),
            ];
        }

        return $this->csvWithMetadata($rows, $filename, 'operations', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $request);
    }

    /**
     * @param  array{total:int, service_minutes:int, by_status: array<string,int>, by_priority: array<string,int>, completion_rate: float|null}  $orders
     * @param  array{total:int, by_status: array<string,int>, by_priority: array<string,int>, overdue:int, completion_rate: float|null}  $tasks
     * @param  array{total:int, completed:int, planned_distance_km:float, planned_minutes:int, per_user: array<int, array{user: User, count:int, distance_km:float, minutes:int}>}  $tours
     */
    private function exportPdf(array $orders, array $tasks, array $tours, string $from, string $to, string $scope, Request $request): SymfonyResponse {
        $filename = sprintf('operations_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.operations', [
            'orders' => $orders,
            'tasks' => $tasks,
            'tours' => $tours,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $filename, request: $request, reportCode: 'operations', filters: ['from' => $from, 'to' => $to, 'scope' => $scope]);
    }
}
