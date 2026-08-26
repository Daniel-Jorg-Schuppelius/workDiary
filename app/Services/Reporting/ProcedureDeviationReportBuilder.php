<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\Procedure\{ProcedureDeviationSeverity, ProcedureDeviationType};
use App\Models\{ProcedureDeviation, ProcedureTemplate};
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Prozedur-Abweichungs-Report (Feature 026, MVP-713 — Vollscan G8): wertet
 * {@see ProcedureDeviation} über Prozedurvorlage, Typ, Schweregrad,
 * Risikoakzeptanz und Folgemaßnahme aus. „Folgemaßnahme" = verknüpfter
 * offener Punkt ODER Folgeauftrag; „Entscheidung" = Risikoakzeptanz
 * (`risk_accepted_at`) — die Dauer bis dahin ist die einzige Entscheidungs-
 * Zeitspanne, die die Abweichung selbst trägt.
 *
 * Alle Quellmodelle sind org-gescopt (BelongsToOrganization); Vorlagen-Filter
 * laufen über die Kette StepRun → Run → Version → Vorlage.
 *
 * @phpstan-type DeviationRow array{id: int, createdAt: CarbonImmutable, templateId: ?int, templateName: string, stepLabel: string, runSqid: ?string, type: ProcedureDeviationType, severity: ProcedureDeviationSeverity, proposedAction: ?string, hasFollowUp: bool, followUpKind: ?string, riskAcceptedAt: ?CarbonImmutable, decisionHours: ?float, reason: string}
 */
class ProcedureDeviationReportBuilder {
    /**
     * @param  bool|null  $riskAccepted  null = alle, true = nur akzeptiert, false = nur offen
     * @param  bool|null  $withFollowUp  null = alle, true = mit Folge-Punkt/-Auftrag, false = ohne
     * @return array{
     *   rows: list<DeviationRow>,
     *   total: int,
     *   byType: array<string, int>,
     *   bySeverity: array<string, int>,
     *   followUpCount: int,
     *   followUpRate: ?float,
     *   riskAcceptedCount: int,
     *   avgDecisionHours: ?float,
     *   topTemplates: list<array{templateId: ?int, templateName: string, count: int}>,
     * }
     */
    public function build(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $templateId = null,
        ?ProcedureDeviationType $type = null,
        ?ProcedureDeviationSeverity $severity = null,
        ?bool $riskAccepted = null,
        ?bool $withFollowUp = null,
    ): array {
        $query = $this->baseQuery($from, $to, $templateId, $type, $severity, $riskAccepted, $withFollowUp)
            ->with(['stepRun.stepDef', 'stepRun.run.templateVersion.template'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $rows = [];
        $byType = array_fill_keys(array_column(ProcedureDeviationType::cases(), 'value'), 0);
        $bySeverity = array_fill_keys(array_column(ProcedureDeviationSeverity::cases(), 'value'), 0);
        $followUpCount = 0;
        $riskAcceptedCount = 0;
        $decisionHoursSum = 0.0;
        /** @var array<string, array{templateId: ?int, templateName: string, count: int}> $templates */
        $templates = [];

        foreach ($query->get() as $deviation) {
            $row = $this->row($deviation);
            $rows[] = $row;

            $byType[$row['type']->value]++;
            $bySeverity[$row['severity']->value]++;
            if ($row['hasFollowUp']) {
                $followUpCount++;
            }
            if ($row['riskAcceptedAt'] !== null) {
                $riskAcceptedCount++;
                $decisionHoursSum += $row['decisionHours'] ?? 0.0;
            }

            $key = (string) ($row['templateId'] ?? '-');
            $templates[$key] ??= ['templateId' => $row['templateId'], 'templateName' => $row['templateName'], 'count' => 0];
            $templates[$key]['count']++;
        }

        $total = count($rows);
        usort($templates, static fn(array $a, array $b): int => [$b['count'], $a['templateName']] <=> [$a['count'], $b['templateName']]);

        return [
            'rows' => $rows,
            'total' => $total,
            'byType' => $byType,
            'bySeverity' => $bySeverity,
            'followUpCount' => $followUpCount,
            'followUpRate' => $total > 0 ? round($followUpCount / $total * 100, 1) : null,
            'riskAcceptedCount' => $riskAcceptedCount,
            'avgDecisionHours' => $riskAcceptedCount > 0 ? round($decisionHoursSum / $riskAcceptedCount, 1) : null,
            'topTemplates' => array_slice($templates, 0, 10),
        ];
    }

    /**
     * Abweichungen je Periode, gestapelt nach Schweregrad (Bänder = Enum-Reihenfolge).
     *
     * @param  list<DeviationRow>  $rows
     * @param  'day'|'week'|'month'|'quarter'  $granularity
     * @param  list<array{key: string, label: string, shortLabel: string}>  $buckets
     * @return list<array<string, string|int>>
     */
    public function severitySeries(array $rows, string $granularity, array $buckets): array {
        if ($rows === []) {
            return []; // Leerzustand statt Null-Serie (§Diagramm-UX).
        }

        $severityValues = array_column(ProcedureDeviationSeverity::cases(), 'value');
        /** @var array<string, array<string, int>> $byKey */
        $byKey = [];
        foreach ($buckets as $bucket) {
            $byKey[$bucket['key']] = array_fill_keys($severityValues, 0);
        }
        foreach ($rows as $row) {
            $key = ChartBucket::keyLabel($granularity, $row['createdAt'])[0];
            if (isset($byKey[$key])) {
                $byKey[$key][$row['severity']->value]++;
            }
        }

        $series = [];
        foreach ($buckets as $bucket) {
            $series[] = ['x' => $bucket['shortLabel']] + $byKey[$bucket['key']];
        }

        return $series;
    }

    /**
     * Vorlagen mit Abweichungen im Zeitraum (Filteroptionen der Seite).
     *
     * @return Collection<int, ProcedureTemplate>
     */
    public function templateOptions(): Collection {
        return ProcedureTemplate::query()->orderBy('name')->get(['id', 'name', 'code']);
    }

    /** @return Builder<ProcedureDeviation> */
    private function baseQuery(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $templateId,
        ?ProcedureDeviationType $type,
        ?ProcedureDeviationSeverity $severity,
        ?bool $riskAccepted,
        ?bool $withFollowUp,
    ): Builder {
        return ProcedureDeviation::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($templateId !== null, fn(Builder $q) => $q->whereHas(
                'stepRun.run.templateVersion',
                fn(Builder $v) => $v->where('procedure_template_id', $templateId),
            ))
            ->when($type !== null, fn(Builder $q) => $q->where('deviation_type', $type?->value))
            ->when($severity !== null, fn(Builder $q) => $q->where('severity', $severity?->value))
            ->when($riskAccepted === true, fn(Builder $q) => $q->whereNotNull('risk_accepted_at'))
            ->when($riskAccepted === false, fn(Builder $q) => $q->whereNull('risk_accepted_at'))
            ->when($withFollowUp === true, fn(Builder $q) => $q->where(
                fn(Builder $w) => $w->whereNotNull('open_issue_id')->orWhereNotNull('follow_up_diary_entry_id'),
            ))
            ->when($withFollowUp === false, fn(Builder $q) => $q->whereNull('open_issue_id')->whereNull('follow_up_diary_entry_id'));
    }

    /** @return DeviationRow */
    private function row(ProcedureDeviation $deviation): array {
        $stepRun = $deviation->stepRun;
        $run = $stepRun?->run;
        $template = $run?->templateVersion?->template;
        $createdAt = CarbonImmutable::parse((string) $deviation->created_at);
        $riskAcceptedAt = $deviation->risk_accepted_at !== null ? CarbonImmutable::parse((string) $deviation->risk_accepted_at) : null;

        $followUpKind = $deviation->open_issue_id !== null
            ? 'open_issue'
            : ($deviation->follow_up_diary_entry_id !== null ? 'diary_entry' : null);

        return [
            'id' => (int) $deviation->id,
            'createdAt' => $createdAt,
            'templateId' => $template?->id !== null ? (int) $template->id : null,
            'templateName' => $template !== null ? (string) $template->name : '–',
            'stepLabel' => $stepRun?->stepDef !== null ? (string) $stepRun->stepDef->label : '–',
            'runSqid' => $run?->sqid,
            'type' => $deviation->deviation_type,
            'severity' => $deviation->severity,
            'proposedAction' => $deviation->proposed_action?->value,
            'hasFollowUp' => $followUpKind !== null,
            'followUpKind' => $followUpKind,
            'riskAcceptedAt' => $riskAcceptedAt,
            // Zeit bis Entscheidung: Anlage → Risikoakzeptanz, in Stunden (nie negativ).
            'decisionHours' => $riskAcceptedAt !== null ? round(max(0.0, $createdAt->diffInSeconds($riskAcceptedAt, false)) / 3600, 1) : null,
            'reason' => (string) $deviation->reason_text,
        ];
    }
}
