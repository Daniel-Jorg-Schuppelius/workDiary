<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourPlanActualReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Diary\TourStop;
use App\Models\Platform\User;
use App\Support\Query\DateRange;
use App\Support\{Sqid, Tz};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Plan-Ist der Touren (MVP-1184): je Stopp geplante und tatsächliche
 * Ankunft, geplante und tatsächliche Verweildauer, Abweichungen; Summen je
 * Fahrer und Auftragsart. Grundlage für die Erfahrungswerte der Servicedauer.
 */
class TourPlanActualReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public const REPORT_CODE = 'tour-plan-actual';

    /** Der eigene Plan-Ist-Vergleich ist persönlich; die Sicht auf alle Fahrer braucht das Exportrecht. */
    protected function exportIsPersonal(Request $request): bool {
        $userId = Sqid::decodeOrNumeric(User::class, $request->string('user')->toString());

        return $userId !== null && $userId === (int) Auth::id();
    }

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize(Permission::TourViewAny->value);
        /** @var User $auth */
        $auth = Auth::user();
        [$from, $to] = $this->resolveRange($request);

        $users = $auth->isAdmin() || $auth->can(Permission::DispatchManage->value)
            ? User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])
            : User::inCurrentOrganization()->whereKey($auth->id)->get(['id', 'name']);
        $userId = Sqid::decodeOrNumeric(User::class, $request->string('user')->toString());
        $only = $users->count() === 1 ? $users->first() : null;
        if (! $users->contains('id', $userId)) {
            $userId = $only !== null ? (int) $only->id : null;
        }

        $rows = $this->rows($from, $to, $userId, array_values($users->pluck('id')->map(static fn($id): int => (int) $id)->all()));
        $totals = $this->totals($rows);
        $byType = $this->byEntryType($rows);
        $filters = ['user' => $userId, 'from' => $from->toDateString(), 'to' => $to->toDateString()];

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($rows, $totals, $from, $to, $request, $filters);
        }

        return view('reports.tour-plan-actual', [
            'users' => $users,
            'userId' => $userId,
            'rows' => $rows,
            'totals' => $totals,
            'byType' => $byType,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /**
     * @param  list<int>  $visibleUserIds
     * @return list<array{stop: TourStop, arrival_delta: int|null, planned_service: int|null, actual_service: int|null, service_delta: int|null}>
     */
    private function rows(CarbonImmutable $from, CarbonImmutable $to, ?int $userId, array $visibleUserIds): array {
        $stops = TourStop::query()
            ->with(['tour:id,name,tour_date,user_id', 'tour.user:id,name', 'diaryEntry:id,title,entry_type_id,customer_id', 'diaryEntry.entryType:id,label', 'diaryEntry.customer:id,name'])
            ->whereHas('tour', function ($q) use ($from, $to, $userId, $visibleUserIds): void {
                $q->whereBetween('tour_date', DateRange::days($from, $to))
                    ->whereIn('user_id', $userId !== null ? [$userId] : $visibleUserIds);
            })
            ->whereNotNull('arrived_at')
            ->orderBy('tour_id')
            ->orderBy('position')
            ->get();

        $rows = [];
        foreach ($stops as $stop) {
            if ($stop->arrived_at === null) {
                continue;
            }
            $arrivalDelta = $stop->planned_arrival_at !== null ? (int) round($stop->planned_arrival_at->diffInMinutes($stop->arrived_at, false)) : null;
            $plannedService = $stop->planned_arrival_at !== null && $stop->planned_departure_at !== null
                ? max(0, (int) round($stop->planned_arrival_at->diffInMinutes($stop->planned_departure_at, false)) - (int) $stop->waiting_minutes)
                : null;
            $actualService = $stop->departed_at !== null ? (int) round($stop->arrived_at->diffInMinutes($stop->departed_at, true)) : null;
            $rows[] = [
                'stop' => $stop,
                'arrival_delta' => $arrivalDelta,
                'planned_service' => $plannedService,
                'actual_service' => $actualService,
                'service_delta' => $plannedService !== null && $actualService !== null ? $actualService - $plannedService : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{stop: TourStop, arrival_delta: int|null, planned_service: int|null, actual_service: int|null, service_delta: int|null}>  $rows
     * @return array{stops: int, done: int, not_met: int, avg_arrival_delta: float|null, late_share: float|null, avg_service_delta: float|null}
     */
    private function totals(array $rows): array {
        $arrival = [];
        $service = [];
        $done = 0;
        $notMet = 0;
        foreach ($rows as $row) {
            if ($row['arrival_delta'] !== null) {
                $arrival[] = $row['arrival_delta'];
            }
            if ($row['service_delta'] !== null) {
                $service[] = $row['service_delta'];
            }
            if ($row['stop']->outcome === TourStop::OUTCOME_DONE) {
                $done++;
            } elseif ($row['stop']->outcome === TourStop::OUTCOME_NOT_MET) {
                $notMet++;
            }
        }
        $late = count(array_filter($arrival, static fn(int $d): bool => $d > 5));

        return [
            'stops' => count($rows),
            'done' => $done,
            'not_met' => $notMet,
            'avg_arrival_delta' => $arrival !== [] ? round(array_sum($arrival) / count($arrival), 1) : null,
            'late_share' => $arrival !== [] ? round($late / count($arrival) * 100, 1) : null,
            'avg_service_delta' => $service !== [] ? round(array_sum($service) / count($service), 1) : null,
        ];
    }

    /**
     * @param  list<array{stop: TourStop, arrival_delta: int|null, planned_service: int|null, actual_service: int|null, service_delta: int|null}>  $rows
     * @return list<array{label: string, stops: int, avg_planned: float|null, avg_actual: float|null}>
     */
    private function byEntryType(array $rows): array {
        $groups = [];
        foreach ($rows as $row) {
            if ($row['actual_service'] === null) {
                continue;
            }
            $label = (string) ($row['stop']->diaryEntry->entryType->label ?? __('Ohne Auftragsart'));
            $groups[$label] ??= ['label' => $label, 'stops' => 0, 'planned' => [], 'actual' => []];
            $groups[$label]['stops']++;
            $groups[$label]['actual'][] = $row['actual_service'];
            if ($row['planned_service'] !== null) {
                $groups[$label]['planned'][] = $row['planned_service'];
            }
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        $result = [];
        foreach ($groups as $group) {
            $result[] = [
                'label' => $group['label'],
                'stops' => $group['stops'],
                'avg_planned' => $group['planned'] !== [] ? round(array_sum($group['planned']) / count($group['planned']), 1) : null,
                'avg_actual' => round(array_sum($group['actual']) / count($group['actual']), 1),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{stop: TourStop, arrival_delta: int|null, planned_service: int|null, actual_service: int|null, service_delta: int|null}>  $rows
     * @param  array<string, mixed>  $totals
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, array $totals, CarbonImmutable $from, CarbonImmutable $to, Request $request, array $filters): SymfonyResponse {
        $out = [[
            (string) __('reporting.csv.date'),
            (string) __('Tour'),
            (string) __('reporting.csv.driver'),
            (string) __('Auftrag'),
            (string) __('Auftragsart'),
            (string) __('Kunde'),
            (string) __('Ankunft geplant'),
            (string) __('Ankunft Ist'),
            (string) __('Abweichung Ankunft (min)'),
            (string) __('Dauer geplant (min)'),
            (string) __('Dauer Ist (min)'),
            (string) __('Abweichung Dauer (min)'),
            (string) __('Ergebnis'),
        ]];
        foreach ($rows as $row) {
            $stop = $row['stop'];
            $out[] = [
                $stop->tour->tour_date?->toDateString() ?? '',
                (string) ($stop->tour->name ?? ('#' . $stop->tour_id)),
                (string) ($stop->tour->user->name ?? ''),
                (string) ($stop->diaryEntry->title ?? ''),
                (string) ($stop->diaryEntry->entryType->label ?? ''),
                (string) ($stop->diaryEntry->customer->name ?? ''),
                Tz::toLocal($stop->planned_arrival_at)?->format('Y-m-d H:i') ?? '',
                Tz::toLocal($stop->arrived_at)?->format('Y-m-d H:i') ?? '',
                $row['arrival_delta'] !== null ? (string) $row['arrival_delta'] : '',
                $row['planned_service'] !== null ? (string) $row['planned_service'] : '',
                $row['actual_service'] !== null ? (string) $row['actual_service'] : '',
                $row['service_delta'] !== null ? (string) $row['service_delta'] : '',
                (string) $stop->outcome,
            ];
        }
        $out[] = [(string) __('reporting.csv.total'), '', '', (string) $totals['stops'], '', '', '', '', $totals['avg_arrival_delta'] !== null ? NumberHelper::toUSFormat($totals['avg_arrival_delta'], 1) : '', '', '', $totals['avg_service_delta'] !== null ? NumberHelper::toUSFormat($totals['avg_service_delta'], 1) : '', ''];

        return $this->csvWithMetadata($out, sprintf('tour-plan-ist_%s_%s.csv', $from->toDateString(), $to->toDateString()), self::REPORT_CODE, $filters, $request);
    }
}
