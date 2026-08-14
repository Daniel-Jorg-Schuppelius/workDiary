<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\Applications\{ApplicationContractNegotiation, ApplicationOpportunity, JobApplication};
use App\Models\User;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Bewerbungs-/Ausschreibungsberichte (Feature 068, MVP-188/194/198):
 * Pipeline, Angebotswert, Trefferquote, Verlustgründe, Bewerber-Trichter,
 * Quellkanäle, Besetzungsdauer und offene Vertragsverhandlungen —
 * ausschließlich aggregierte Kennzahlen, keine Bewerberdetails.
 */
class ApplicationsReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|Response {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        // Admin trägt über die Rollenmatrix ohnehin alle Rechte.
        $canTender = $user->can(P::TenderViewAny->value);
        $canRecruiting = $user->can(P::RecruitingViewAny->value);
        abort_unless($canTender || $canRecruiting, 403);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        // Status = Bewerbungs-Workflow (JobApplication::STATUSES) — wirkt nur
        // auf den Recruiting-Block; Ausschreibungen tragen ein anderes Enum.
        $filters = $this->standardFilters($request, ['status'], $fromDate, $toDate, JobApplication::STATUSES);

        $tenders = $canTender ? $this->aggregateTenders($from, $to) : null;
        $recruiting = $canRecruiting ? $this->aggregateRecruiting($from, $to, $filters->status) : null;
        $contracts = $this->aggregateContracts(); // Guard oben: mindestens ein Bereich sichtbar

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($tenders, $recruiting, $contracts, $from, $to, $filters->toAuditArray(), $request);
        }

        $statusOptions = [];
        foreach (JobApplication::STATUSES as $status) {
            $statusOptions[$status] = (string) __("values.$status");
        }

        return view('reports.applications', [
            'from' => $from,
            'to' => $to,
            'tenders' => $tenders,
            'recruiting' => $recruiting,
            'contracts' => $contracts,
            'standardFilters' => $filters,
            'filterFields' => ['status'],
            'statusOptions' => $statusOptions,
            'monthlySeries' => $this->monthlySeries($recruiting['monthly'] ?? [], $fromDate, $toDate),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($fromDate, $toDate)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($fromDate, $toDate)),
            'funnelSeries' => $this->funnelSeries($recruiting['pipeline'] ?? []),
        ]);
    }

    /**
     * Bewerbungseingang je Bucket (adaptiv zur Header-Granularität;
     * Eingangsdatum, sonst Anlagedatum) — nur aggregierte Zählungen, keine
     * Bewerberdaten (PII bleibt verschlüsselt). Leere Serie statt Null-Linie
     * (§Diagramm-UX).
     *
     * @param  array<string, int>  $monthly
     * @return list<array{x: string, y: int}>
     */
    private function monthlySeries(array $monthly, CarbonImmutable $from, CarbonImmutable $to): array {
        if (array_sum($monthly) === 0) {
            return [];
        }

        $series = [];
        foreach ($this->buildBucketsInRange($from, $to) as $bucket) {
            $series[] = ['x' => $bucket['shortLabel'], 'y' => $monthly[$bucket['key']] ?? 0];
        }

        return $series;
    }

    /**
     * Bewerber-Funnel: Anzahl je Workflow-Stufe in Workflow-Reihenfolge
     * (JobApplication::STATUSES) — bewusst NICHT nach Größe sortiert.
     *
     * @param  array<string, int>  $pipeline
     * @return list<array{x: string, y: int}>
     */
    private function funnelSeries(array $pipeline): array {
        $series = [];
        foreach (JobApplication::STATUSES as $status) {
            $count = $pipeline[$status] ?? 0;
            if ($count > 0) {
                $series[] = ['x' => (string) __("values.$status"), 'y' => $count];
            }
        }

        return $series;
    }

    /**
     * @return array{pipeline: array<string, array{count:int, value:float}>, win_rate: float|null, loss_reasons: array<string, int>, upcoming: int}
     */
    private function aggregateTenders(string $from, string $to): array {
        $pipeline = [];
        $lossReasons = [];
        $won = 0;
        $decided = 0;

        foreach (ApplicationOpportunity::query()->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->get(['status', 'estimated_value', 'loss_reason']) as $opportunity) {
            $status = (string) $opportunity->status;
            $pipeline[$status] ??= ['count' => 0, 'value' => 0.0];
            $pipeline[$status]['count']++;
            $pipeline[$status]['value'] += (float) $opportunity->estimated_value;

            if (in_array($status, ['won', 'lost'], true)) {
                $decided++;
                if ($status === 'won') {
                    $won++;
                } elseif ($opportunity->loss_reason !== null) {
                    $reason = (string) $opportunity->loss_reason;
                    $lossReasons[$reason] = ($lossReasons[$reason] ?? 0) + 1;
                }
            }
        }
        arsort($lossReasons);

        return [
            'pipeline' => $pipeline,
            'win_rate' => $decided > 0 ? round($won / $decided * 100, 1) : null,
            'loss_reasons' => array_slice($lossReasons, 0, 10, true),
            'upcoming' => ApplicationOpportunity::query()
                ->whereIn('status', ApplicationOpportunity::OPEN_STATUSES)
                ->whereNotNull('submission_deadline')
                ->whereBetween('submission_deadline', [now()->toDateString(), now()->addDays(14)->toDateString()])
                ->count(),
        ];
    }

    /**
     * @return array{pipeline: array<string, int>, sources: array<string, int>, monthly: array<string, int>, avg_days_to_accept: float|null}
     */
    private function aggregateRecruiting(string $from, string $to, ?string $status): array {
        $granularity = $this->bucketGranularity(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
        $pipeline = [];
        $sources = [];
        $monthly = [];
        $acceptDays = [];

        $applications = JobApplication::query()
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->when($status !== null, fn($q) => $q->where('status', $status))
            ->get(['status', 'source', 'received_at', 'created_at', 'updated_at']);
        foreach ($applications as $application) {
            $pipeline[(string) $application->status] = ($pipeline[(string) $application->status] ?? 0) + 1;
            $sources[(string) $application->source] = ($sources[(string) $application->source] ?? 0) + 1;
            $date = $application->received_at ?? $application->created_at;
            $monthKey = $date !== null ? ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $date))[0] : null;
            if ($monthKey !== null) {
                $monthly[$monthKey] = ($monthly[$monthKey] ?? 0) + 1;
            }
            if ($application->status === 'accepted' && $application->received_at !== null) {
                $acceptDays[] = (float) $application->received_at->diffInDays($application->updated_at);
            }
        }

        return [
            'pipeline' => $pipeline,
            'sources' => $sources,
            'monthly' => $monthly,
            'avg_days_to_accept' => $acceptDays !== [] ? round(array_sum($acceptDays) / count($acceptDays), 1) : null,
        ];
    }

    /**
     * @return array{open: int, open_blockers: int, due_soon: int}
     */
    private function aggregateContracts(): array {
        return [
            'open' => ApplicationContractNegotiation::query()->whereNull('decision')->count(),
            'open_blockers' => \App\Models\Applications\ApplicationContractReview::query()
                ->where('severity', 'blocker')->where('status', 'open')->count(),
            'due_soon' => ApplicationContractNegotiation::query()
                ->whereNull('decision')
                ->whereNotNull('due_on')
                ->whereBetween('due_on', [now()->toDateString(), now()->addDays(14)->toDateString()])
                ->count(),
        ];
    }

    /**
     * @param array{pipeline: array<string, array{count:int, value:float}>, win_rate: float|null, loss_reasons: array<string, int>, upcoming: int}|null $tenders
     * @param array{pipeline: array<string, int>, sources: array<string, int>, monthly: array<string, int>, avg_days_to_accept: float|null}|null $recruiting
     * @param array{open: int, open_blockers: int, due_soon: int}|null $contracts
     * @param array<string, int|string> $filters
     */
    private function exportCsv(?array $tenders, ?array $recruiting, ?array $contracts, string $from, string $to, array $filters, Request $request): Response {
        $rows = [['Bereich', 'Schlüssel', 'Anzahl', 'Wert €']];
        foreach (($tenders['pipeline'] ?? []) as $status => $row) {
            $rows[] = ['Ausschreibungen', $status, $row['count'], NumberHelper::toUSFormat((float) $row['value'], 2)];
        }
        if ($tenders !== null) {
            $rows[] = ['Ausschreibungen', 'TREFFERQUOTE_%', $tenders['win_rate'] ?? '', ''];
            foreach ($tenders['loss_reasons'] as $reason => $count) {
                $rows[] = ['Verlustgrund', $reason, $count, ''];
            }
        }
        foreach (($recruiting['pipeline'] ?? []) as $status => $count) {
            $rows[] = ['Bewerbungen', $status, $count, ''];
        }
        foreach (($recruiting['sources'] ?? []) as $source => $count) {
            $rows[] = ['Quellkanal', $source, $count, ''];
        }
        if ($contracts !== null) {
            $rows[] = ['Verträge', 'offen', $contracts['open'], ''];
            $rows[] = ['Verträge', 'offene Blocker', $contracts['open_blockers'], ''];
            $rows[] = ['Verträge', 'fällig ≤ 14 Tage', $contracts['due_soon'], ''];
        }

        return $this->csvWithMetadata($rows, sprintf('applications_%s_%s.csv', $from, $to), 'applications', $filters, $request);
    }
}
