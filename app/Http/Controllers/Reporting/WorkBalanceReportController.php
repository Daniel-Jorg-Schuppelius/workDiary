<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkBalanceReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\User;
use App\Services\Reporting\{PeriodBalance, ReportFilters, WorkBalanceCalculator};
use App\Support\Sqid;
use Carbon\{Carbon, CarbonImmutable};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Unified Work-Balance report: combines Soll (FlexCalculator),
 * Anwesenheit (Attendance), Erfassung (TimeEntry by kind/activity) and
 * the resulting balance for the currently selected global date range
 * (Header-Widget) or an explicit `?from=&to=` / `?year=&month=` override.
 */
class WorkBalanceReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    // A9: liefert auditExport für den PDF-Export.
    use WritesReportCsv;

    /** Ab dieser Zeitraumlänge wird die Tagesserie wochenweise aggregiert. */
    private const WEEKLY_THRESHOLD_DAYS = 62;

    public function __construct(protected WorkBalanceCalculator $calc) {}

    public function index(Request $request): View|SymfonyResponse {
        /** @var User $authUser */
        $authUser = Auth::user();
        $user = $this->resolveTargetUser($request, $authUser);
        $isAdmin = $authUser->isAdmin();

        [$from, $to, $label] = $this->resolveRange($request);

        // Standardfilter (Feature 002): 'user' bleibt über resolveTargetUser
        // führend (Admin-Gate + Org-Grenze); das Set spiegelt den aufgelösten
        // Nutzer für Partial-Preselect, Links und Audit. 'team' engt nur die
        // Mitarbeiter-Auswahlliste ein — der Report bleibt eine Ein-Nutzer-Sicht.
        $filters = $this->standardFilters($request, ['user', 'team'], $from, $to);
        if ($filters->userId !== (int) $user->id) {
            $filters = new ReportFilters(from: $from, to: $to, userId: (int) $user->id, teamId: $filters->teamId);
        }

        $period = $this->calc->range($user, $from, $to);

        if ($request->query('export') === 'pdf') {
            $filename = sprintf(
                'arbeitsbilanz-%s-%s_%s.pdf',
                $user->id,
                $from->format('Ymd'),
                $to->format('Ymd'),
            );

            return $this->pdfDownload('reports.work-balance-pdf', [
                'user' => $user,
                'period' => $period,
                'label' => $label,
            ], $filename, request: $request, reportCode: 'work-balance', filters: array_merge(
                ['user_id' => $user->id],
                $filters->toAuditArray(),
            ));
        }

        $filterOptions = [];
        if ($isAdmin) {
            $filterOptions = $this->standardFilterOptions(['user', 'team'], $filters);
            if ($filters->teamId !== null && isset($filterOptions['filterUsers'])) {
                // Team wählt die Mitarbeiterliste vor; der aktuell angezeigte
                // Nutzer bleibt sichtbar, auch wenn er nicht Team-Mitglied ist.
                $teamIds = $filters->teamUserIds();
                $filterOptions['filterUsers'] = $filterOptions['filterUsers']
                    ->filter(fn($option): bool => in_array((int) $option->getKey(), $teamIds, true) || (int) $option->getKey() === (int) $user->id)
                    ->values();
            }
        }

        [$dailySeries, $dailyIsWeekly] = $this->istSollSeries($period);
        $monthlySeries = $this->monthlyIstSollSeries($period);

        /** @var View $view */
        $view = view('reports.work-balance', [
            'user' => $user,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'isAdmin' => $isAdmin,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'dailySeries' => $dailySeries,
            'dailySeriesLabel' => $dailyIsWeekly ? __('Ist- und Soll-Stunden je Kalenderwoche') : __('Ist- und Soll-Stunden je Tag'),
            'dailyMedian' => $dailySeries === [] ? null : NumberHelper::median(array_column($dailySeries, 'y')),
            'monthlySeries' => $monthlySeries,
            'monthlyMedian' => $monthlySeries === [] ? null : NumberHelper::median(array_column($monthlySeries, 'y')),
            ...$filterOptions,
        ]);

        return $view;
    }

    /**
     * Ist-Stunden (Erfasst) vs. Soll je Tag — bei langen Zeiträumen je
     * ISO-Woche. Der Flex-Saldo selbst kann negativ werden und ist mit den
     * Chart-Komponenten nicht darstellbar (nur nicht-negative Werte); die
     * Ist/Soll-Gruppierung zeigt dieselbe Abweichung vorzeichenfrei.
     *
     * @return array{0: list<array{x: string, y: float, y2: float}>, 1: bool} [Serie, wochenweise?]
     */
    private function istSollSeries(PeriodBalance $period): array {
        // Ohne erfasste Daten Leerzustand statt Soll-only-Achse (§Diagramm-UX) —
        // das Default-Arbeitszeitmodell liefert auch in leeren Orgs ein Soll > 0.
        if ($period->trackedMinutes === 0 && $period->attendanceMinutes === 0) {
            return [[], false];
        }

        $weekly = count($period->days) > self::WEEKLY_THRESHOLD_DAYS;
        $buckets = [];
        foreach ($period->days as $day) {
            $date = Carbon::parse($day->date);
            $key = $weekly ? sprintf('KW %02d/%04d', $date->isoWeek, $date->isoWeekYear) : $date->isoFormat('DD.MM.');
            $buckets[$key] ??= ['tracked' => 0, 'target' => 0];
            $buckets[$key]['tracked'] += $day->trackedMinutes;
            $buckets[$key]['target'] += $day->targetMinutes;
        }

        $series = [];
        foreach ($buckets as $key => $minutes) {
            $series[] = [
                'x' => (string) $key,
                'y' => round($minutes['tracked'] / 60, 1),
                'y2' => round($minutes['target'] / 60, 1),
            ];
        }

        return [$series, $weekly];
    }

    /**
     * Ist-Stunden (Erfasst) vs. Soll je Monat (Saldo = sichtbare Differenz;
     * Median über die Monats-Ist-Werte).
     *
     * @return list<array{x: string, y: float, y2: float}>
     */
    private function monthlyIstSollSeries(PeriodBalance $period): array {
        // Leerzustand analog istSollSeries() (§Diagramm-UX).
        if ($period->trackedMinutes === 0 && $period->attendanceMinutes === 0) {
            return [];
        }

        $locale = app()->getLocale();
        $buckets = [];
        foreach ($period->days as $day) {
            $date = Carbon::parse($day->date);
            $date->locale($locale);
            $key = $date->format('Y-m');
            $buckets[$key] ??= ['label' => $date->isoFormat('MMM YY'), 'tracked' => 0, 'target' => 0];
            $buckets[$key]['tracked'] += $day->trackedMinutes;
            $buckets[$key]['target'] += $day->targetMinutes;
        }

        $series = [];
        foreach ($buckets as $bucket) {
            $series[] = [
                'x' => $bucket['label'],
                'y' => round($bucket['tracked'] / 60, 1),
                'y2' => round($bucket['target'] / 60, 1),
            ];
        }

        return $series;
    }

    /**
     * Wer wird angezeigt? Standard: eingeloggter Nutzer.
     * Admin darf per ?user=ID jeden anderen Nutzer sehen, sonst 403.
     */
    private function resolveTargetUser(Request $request, User $authUser): User {
        if (! $request->filled('user')) {
            return $authUser;
        }

        $rawUser = (string) $request->query('user', '');
        $requestedId = Sqid::decodeOrNumeric(User::class, $rawUser);
        if ($requestedId === null || $requestedId === (int) $authUser->id) {
            return $authUser;
        }

        if (! $authUser->isAdmin()) {
            throw new AccessDeniedHttpException('Nur Admins dürfen die Arbeitsbilanz anderer Nutzer einsehen.');
        }

        // Mandantengrenze: nur Nutzer der eigenen Organisation (User hat keinen
        // globalen OrganizationScope, Whitebox-Befund 2026-07).
        $target = User::query()
            ->where('organization_id', $authUser->organization_id)
            ->find($requestedId);
        if (! $target instanceof User) {
            throw new AccessDeniedHttpException('Nutzer nicht gefunden.');
        }

        return $target;
    }

    /**
     * Precedence: explicit ?from/?to → ?year(+?month) → global header range.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveRange(Request $request): array {
        if ($request->filled('from') && $request->filled('to')) {
            // Guard statt Roh-Parse (Vollscan 2026-08-23, B10): Müll-Input
            // fällt auf den globalen Zeitraum zurück, verdrehte Grenzen tauschen.
            [$from, $to] = $this->resolveRangeWithDefault($request, fn (): array => $this->globalDateRangeBounds());

            return [$from, $to, $this->formatLabel($from, $to)];
        }

        if ($request->filled('year') && ! $request->filled('month')) {
            $year = max(2000, min(2100, (int) $request->integer('year')));
            $tmp = CarbonImmutable::create($year, 1, 1);
            $base = $tmp instanceof CarbonImmutable ? $tmp : CarbonImmutable::now();
            $from = $base->startOfYear();
            $to = $from->endOfYear();

            return [$from, $to, (string) $year];
        }

        if ($request->filled('year') && $request->filled('month')) {
            $year = max(2000, min(2100, (int) $request->integer('year')));
            $month = max(1, min(12, (int) $request->integer('month')));
            $tmp = CarbonImmutable::create($year, $month, 1);
            $base = $tmp instanceof CarbonImmutable ? $tmp : CarbonImmutable::now();
            $from = $base->startOfMonth();
            $to = $from->endOfMonth();
            CarbonImmutable::setLocale((string) app()->getLocale());

            return [$from, $to, $from->isoFormat('MMMM YYYY')];
        }

        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();
        $label = $range['label'] !== '' ? $range['label'] : $this->formatLabel($from, $to);

        return [$from, $to, $label];
    }

    private function formatLabel(CarbonImmutable $from, CarbonImmutable $to): string {
        return sprintf('%s – %s', $from->format('d.m.Y'), $to->format('d.m.Y'));
    }
}
