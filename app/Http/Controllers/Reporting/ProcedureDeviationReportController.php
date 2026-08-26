<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Procedure\{ProcedureDeviationSeverity, ProcedureDeviationType};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{ProcedureDeviation, ProcedureTemplate};
use App\Services\Reporting\ProcedureDeviationReportBuilder;
use App\Support\{CarbonFmt, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Prozedur-Abweichungen (Feature 026, MVP-713 — Vollscan G8): Anzahl je Typ
 * und Schweregrad, Quote mit Folge-Punkt/-Auftrag, Top-Prozeduren und Ø Zeit
 * bis zur Risikoentscheidung. Recht wie die Abweichungserfassung selbst
 * (`viewAny` ProcedureDeviation → procedure.deviation.view). Exporte CSV/XLSX/
 * PDF über das Report-Framework (Feature 002).
 */
class ProcedureDeviationReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly ProcedureDeviationReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        Gate::authorize('viewAny', ProcedureDeviation::class);

        [$from, $to] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, [], $from, $to);
        $label = CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to);

        $templateId = Sqid::decodeOrNumeric(ProcedureTemplate::class, $request->query('template'));
        if ($templateId !== null && ! ProcedureTemplate::query()->whereKey($templateId)->exists()) {
            $templateId = null; // Fremde/unbekannte Vorlage: still ignorieren (org-gescopt).
        }
        $type = ProcedureDeviationType::tryFrom((string) $request->query('type', ''));
        $severity = ProcedureDeviationSeverity::tryFrom((string) $request->query('severity', ''));
        $riskAccepted = $this->triState((string) $request->query('risk', ''));
        $withFollowUp = $this->triState((string) $request->query('follow_up', ''));

        $result = $this->builder->build($from, $to, $templateId, $type, $severity, $riskAccepted, $withFollowUp);

        $exportFilters = $filters->toAuditArray() + array_filter([
            'template_id' => $templateId,
            'type' => $type?->value,
            'severity' => $severity?->value,
            'risk' => $riskAccepted === null ? null : ($riskAccepted ? 'yes' : 'no'),
            'follow_up' => $withFollowUp === null ? null : ($withFollowUp ? 'yes' : 'no'),
        ], static fn($v): bool => $v !== null);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($result['rows'], $from, $to, $exportFilters, $request);
        }

        $typeSeries = $this->typeSeries($result['byType']);
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($result, $label, $from, $to, $typeSeries, $exportFilters, $request);
        }

        $granularity = $this->bucketGranularity($from, $to);

        return view('reports.procedure-deviations', [
            'result' => $result,
            'rows' => $result['rows'],
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'standardFilters' => $filters,
            'filterFields' => [],
            'templates' => $this->builder->templateOptions(),
            'templateId' => $templateId,
            'type' => $type,
            'severity' => $severity,
            'risk' => $riskAccepted,
            'followUp' => $withFollowUp,
            'linkParams' => $this->linkParams($templateId, $type, $severity, $riskAccepted, $withFollowUp),
            'typeSeries' => $typeSeries,
            'severitySeries' => $this->builder->severitySeries($result['rows'], $granularity, $this->buildBucketsInRange($from, $to)),
            'severityBands' => array_map(static fn(ProcedureDeviationSeverity $s): array => ['key' => $s->value, 'label' => $s->label()], ProcedureDeviationSeverity::cases()),
            'templateSeries' => $this->templateSeries($result['topTemplates']),
            'periodPhrase' => $this->periodPhrase($granularity),
            'periodAxis' => $this->periodAxisLabel($granularity),
        ]);
    }

    /** `yes`/`no` → bool, alles andere → null (kein Filter). */
    private function triState(string $value): ?bool {
        return match ($value) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    /** @return array<string, string> Query-Parameter für Export-/Reset-Links (Sqid statt roher ID). */
    private function linkParams(?int $templateId, ?ProcedureDeviationType $type, ?ProcedureDeviationSeverity $severity, ?bool $risk, ?bool $followUp): array {
        return array_filter([
            'template' => Sqid::encode(ProcedureTemplate::class, $templateId),
            'type' => $type === null ? '' : $type->value,
            'severity' => $severity === null ? '' : $severity->value,
            'risk' => $risk === null ? '' : ($risk ? 'yes' : 'no'),
            'follow_up' => $followUp === null ? '' : ($followUp ? 'yes' : 'no'),
        ], static fn(string $v): bool => $v !== '');
    }

    /**
     * Abweichungen je Typ (nur belegte Typen, absteigend).
     *
     * @param  array<string, int>  $byType
     * @return list<array{x: string, y: int}>
     */
    private function typeSeries(array $byType): array {
        $series = [];
        foreach (ProcedureDeviationType::cases() as $case) {
            $count = $byType[$case->value] ?? 0;
            if ($count > 0) {
                $series[] = ['x' => $case->label(), 'y' => $count];
            }
        }
        usort($series, static fn(array $a, array $b): int => $b['y'] <=> $a['y']);

        return $series;
    }

    /**
     * @param  list<array{templateId: ?int, templateName: string, count: int}>  $topTemplates
     * @return list<array{x: string, y: int}>
     */
    private function templateSeries(array $topTemplates): array {
        return array_map(static fn(array $t): array => ['x' => $t['templateName'], 'y' => $t['count']], $topTemplates);
    }

    /**
     * @param  list<array{id: int, createdAt: CarbonImmutable, templateId: ?int, templateName: string, stepLabel: string, runSqid: ?string, type: ProcedureDeviationType, severity: ProcedureDeviationSeverity, proposedAction: ?string, hasFollowUp: bool, followUpKind: ?string, riskAcceptedAt: ?CarbonImmutable, decisionHours: ?float, reason: string}>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, CarbonImmutable $from, CarbonImmutable $to, array $filters, Request $request): Response {
        $filename = sprintf('prozedur-abweichungen_%s_%s.csv', $from->toDateString(), $to->toDateString());
        $out = [[
            'Datum', 'Prozedur', 'Schritt', 'Typ', 'Schweregrad', 'VorgeschlageneAktion', 'Folgemassnahme', 'RisikoAkzeptiertAm', 'StundenBisEntscheidung', 'Begruendung',
        ]];
        foreach ($rows as $row) {
            $out[] = [
                $row['createdAt']->toDateTimeString(),
                $row['templateName'],
                $row['stepLabel'],
                $row['type']->value,
                $row['severity']->value,
                $row['proposedAction'] ?? '',
                $row['followUpKind'] ?? '',
                $row['riskAcceptedAt']?->toDateTimeString() ?? '',
                $row['decisionHours'] ?? '',
                $row['reason'],
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'procedure-deviations', $filters, $request);
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, total: int, byType: array<string, int>, bySeverity: array<string, int>, followUpCount: int, followUpRate: ?float, riskAcceptedCount: int, avgDecisionHours: ?float, topTemplates: list<array{templateId: ?int, templateName: string, count: int}>}  $result
     * @param  list<array{x: string, y: int}>  $typeSeries
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(array $result, string $label, CarbonImmutable $from, CarbonImmutable $to, array $typeSeries, array $filters, Request $request): SymfonyResponse {
        $filename = sprintf('prozedur-abweichungen_%s_%s.pdf', $from->toDateString(), $to->toDateString());

        return $this->pdfDownload('reports.pdf.procedure-deviations', [
            'result' => $result,
            'label' => $label,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('procedure.report.chart.by_type'),
                'unit' => __('procedure.report.unit'),
                'xLabel' => __('procedure.report.col.type'),
                'yLabel' => __('procedure.report.unit'),
                'series' => $typeSeries,
            ],
        ], $filename, 'landscape', $request, 'procedure-deviations', $filters);
    }
}
