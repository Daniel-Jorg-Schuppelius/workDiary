<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderCockpitController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\Applications\TenderProcedureType;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\Tenders\TenderNoticeMatch;
use App\Models\User;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Vergabe-Cockpit (Feature 108, MVP-631).
 *
 * Der Bewerbungsbericht zeigt Ausschreibungen neben der Personalgewinnung; das
 * Cockpit sieht nur auf die Vergabe — und zwar auf das, was im Vergabegeschäft
 * über Erfolg entscheidet: **Fristen**. Eine versäumte Abgabefrist ist kein
 * verlorenes Angebot, sondern gar keines, und die Frist läuft unabhängig davon,
 * wie gut kalkuliert wurde.
 *
 * Deshalb liegt die Fristensicht neben Pipeline und Trefferquote:
 *
 * - **Fristenlast je Verantwortlichem** — wer wie viele Abgaben gleichzeitig
 *   zu stemmen hat. Eine Person mit vier Abgaben in derselben Woche ist ein
 *   Risiko, auch wenn die Pipeline gesund aussieht.
 * - **Fristfenster** statt Kalenderraster: überfällig, diese Woche, zwei
 *   Wochen, ein Monat, später. Das Fenster sagt, was zu tun ist; ein Datum
 *   allein nicht.
 * - **Trefferquote nur aus entschiedenen Vorgängen.** Laufende Vorgänge
 *   dagegenzurechnen drückte die Quote künstlich — sie sind weder gewonnen
 *   noch verloren.
 *
 * Der Zeitraumfilter greift auf das **Anlagedatum** der Akte; Fristensichten
 * dagegen zeigen immer den offenen Bestand, unabhängig vom Zeitraum: Eine
 * morgen fällige Abgabe verschwindet nicht, weil der Bericht auf den Vormonat
 * eingestellt ist.
 */
class TenderCockpitController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|Response {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->can(P::TenderViewAny->value), 403);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $pipeline = $this->pipeline($from, $to);
        $decision = $this->decision($from, $to);
        $deadlines = $this->deadlineWindows();
        $workload = $this->workload();
        $granularity = $this->bucketGranularity($fromDate, $toDate);
        $valueSeries = $this->valueSeries($fromDate, $toDate, $granularity);
        $radar = $this->radar();
        $procedures = $this->procedures($from, $to);

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->export($pipeline, $decision, $deadlines, $workload, $procedures, $from, $to, $request);
        }

        return view('reports.tender-cockpit', [
            'from' => $from,
            'to' => $to,
            'pipeline' => $pipeline,
            'decision' => $decision,
            'deadlines' => $deadlines,
            'workload' => $workload,
            'radar' => $radar,
            'procedures' => $procedures,
            'valueSeries' => $valueSeries,
            'periodPhrase' => $this->periodPhrase($granularity),
            'periodAxis' => $this->periodAxisLabel($granularity),
            'pipelineSeries' => $this->pipelineSeries($pipeline),
            'deadlineSeries' => $this->deadlineSeries($deadlines),
            'workloadSeries' => $this->workloadSeries($workload),
            'lossSeries' => $this->lossSeries($decision['loss_reasons']),
        ]);
    }

    /**
     * Pipeline im Zeitraum: Anzahl und Wertpotenzial je Status, in der
     * Reihenfolge des Workflows — nicht nach Größe sortiert, sonst liest sich
     * der Trichter falsch herum.
     *
     * @return array<string, array{count: int, value: float}>
     */
    private function pipeline(string $from, string $to): array {
        $rows = [];
        foreach (ApplicationOpportunity::query()
            ->where('kind', '!=', 'inquiry')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get(['status', 'estimated_value']) as $opportunity) {
            $status = (string) $opportunity->status;
            $rows[$status] ??= ['count' => 0, 'value' => 0.0];
            $rows[$status]['count']++;
            $rows[$status]['value'] += (float) $opportunity->estimated_value;
        }

        $ordered = [];
        foreach (ApplicationOpportunity::STATUSES as $status) {
            if (isset($rows[$status])) {
                $ordered[$status] = $rows[$status];
            }
        }

        return $ordered;
    }

    /**
     * Trefferquote und Verlustgründe.
     *
     * Bezugsgröße sind **entschiedene** Vorgänge (gewonnen + verloren).
     * Zurückgezogene zählen nicht als Verlust — wer nicht abgibt, verliert
     * nicht, sondern hat sich entschieden.
     *
     * @return array{submitted: int, won: int, lost: int, withdrawn: int, win_rate: float|null, won_value: float, loss_reasons: array<string, int>}
     */
    private function decision(string $from, string $to): array {
        $counts = ['submitted' => 0, 'won' => 0, 'lost' => 0, 'withdrawn' => 0];
        $lossReasons = [];
        $wonValue = 0.0;

        foreach (ApplicationOpportunity::query()
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get(['status', 'estimated_value', 'loss_reason']) as $opportunity) {
            $status = (string) $opportunity->status;
            if ($status === 'won') {
                $counts['won']++;
                $wonValue += (float) $opportunity->estimated_value;
            } elseif ($status === 'lost') {
                $counts['lost']++;
                $reason = trim((string) $opportunity->loss_reason);
                $reason = $reason === '' ? (string) __('Ohne Angabe') : $reason;
                $lossReasons[$reason] = ($lossReasons[$reason] ?? 0) + 1;
            } elseif ($status === 'withdrawn') {
                $counts['withdrawn']++;
            }
            if (in_array($status, ['submitted', 'post_submission', 'won', 'lost'], true)) {
                $counts['submitted']++;
            }
        }

        arsort($lossReasons);
        $decided = $counts['won'] + $counts['lost'];

        return $counts + [
            'win_rate' => $decided > 0 ? round($counts['won'] / $decided * 100, 1) : null,
            'won_value' => $wonValue,
            'loss_reasons' => $lossReasons,
        ];
    }

    /**
     * Offener Abgabebestand nach Fristfenster — **ohne Zeitraumfilter**: Eine
     * morgen fällige Abgabe verschwindet nicht, weil der Bericht auf den
     * Vormonat eingestellt ist.
     *
     * @return array<string, array{count: int, value: float}>
     */
    private function deadlineWindows(): array {
        $today = CarbonImmutable::today();
        $windows = [
            'overdue' => ['count' => 0, 'value' => 0.0],
            'week' => ['count' => 0, 'value' => 0.0],
            'fortnight' => ['count' => 0, 'value' => 0.0],
            'month' => ['count' => 0, 'value' => 0.0],
            'later' => ['count' => 0, 'value' => 0.0],
            'none' => ['count' => 0, 'value' => 0.0],
        ];

        foreach (ApplicationOpportunity::query()
            ->whereIn('status', ApplicationOpportunity::OPEN_STATUSES)
            ->get(['submission_deadline', 'estimated_value']) as $opportunity) {
            $deadline = $opportunity->submission_deadline;
            $key = match (true) {
                $deadline === null => 'none',
                $deadline->lt($today) => 'overdue',
                $deadline->lte($today->addDays(7)) => 'week',
                $deadline->lte($today->addDays(14)) => 'fortnight',
                $deadline->lte($today->addDays(30)) => 'month',
                default => 'later',
            };
            $windows[$key]['count']++;
            $windows[$key]['value'] += (float) $opportunity->estimated_value;
        }

        return $windows;
    }

    /**
     * Fristenlast je Verantwortlichem: offene Vorgänge, davon in den nächsten
     * 14 Tagen fällig. Wer vier Abgaben in zwei Wochen hat, ist das Risiko —
     * nicht der, der zehn Vorgänge mit Fristen im Herbst führt.
     *
     * @return list<array{name: string, open: int, soon: int, overdue: int, value: float}>
     */
    private function workload(): array {
        $today = CarbonImmutable::today();
        $rows = [];

        foreach (ApplicationOpportunity::query()
            ->with('responsible:id,name')
            ->whereIn('status', ApplicationOpportunity::OPEN_STATUSES)
            ->get() as $opportunity) {
            $responsible = $opportunity->responsible;
            $name = $responsible === null ? (string) __('Ohne Verantwortlichen') : $responsible->name;
            $rows[$name] ??= ['name' => $name, 'open' => 0, 'soon' => 0, 'overdue' => 0, 'value' => 0.0];
            $rows[$name]['open']++;
            $rows[$name]['value'] += (float) $opportunity->estimated_value;

            $deadline = $opportunity->submission_deadline;
            if ($deadline === null) {
                continue;
            }
            if ($deadline->lt($today)) {
                $rows[$name]['overdue']++;
            } elseif ($deadline->lte($today->addDays(14))) {
                $rows[$name]['soon']++;
            }
        }

        // Die dringendste Last zuerst: überfällig schlägt bald, bald schlägt
        // Menge.
        $rows = array_values($rows);
        usort($rows, static fn (array $a, array $b): int => [$b['overdue'], $b['soon'], $b['open']] <=> [$a['overdue'], $a['soon'], $a['open']]);

        return $rows;
    }

    /**
     * Angebotswert je Zeitabschnitt — abgegebene und entschiedene Vorgänge
     * nach Anlagedatum.
     *
     * @return list<array{x: string, y: float}>
     */
    private function valueSeries(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array {
        /** @var 'day'|'week'|'month'|'quarter' $granularity */
        $totals = [];
        foreach (ApplicationOpportunity::query()
            ->whereBetween('created_at', [$from->toDateString() . ' 00:00:00', $to->toDateString() . ' 23:59:59'])
            ->get(['created_at', 'estimated_value']) as $opportunity) {
            $createdAt = $opportunity->created_at;
            if ($createdAt === null) {
                continue;
            }
            [$key] = ChartBucket::keyLabel($granularity, CarbonImmutable::parse($createdAt));
            $totals[$key] = ($totals[$key] ?? 0.0) + (float) $opportunity->estimated_value;
        }

        // Leere Serie statt Null-Linie (§Diagramm-UX).
        if (array_sum($totals) <= 0.0) {
            return [];
        }

        $series = [];
        foreach ($this->buildBucketsInRange($from, $to) as $bucket) {
            $series[] = ['x' => $bucket['shortLabel'], 'y' => round($totals[$bucket['key']] ?? 0.0, 2)];
        }

        return $series;
    }

    /**
     * Verfahrensarten der Vorgänge im Zeitraum — welche Rechtslage der Betrieb
     * tatsächlich bedient.
     *
     * @return array<string, int>
     */
    private function procedures(string $from, string $to): array {
        $rows = [];
        foreach (ApplicationOpportunity::query()
            ->whereNotNull('procedure_type')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get(['procedure_type']) as $opportunity) {
            $type = $opportunity->procedure_type;
            $label = $type instanceof TenderProcedureType ? $type->label() : (string) __('Ohne Angabe');
            $rows[$label] = ($rows[$label] ?? 0) + 1;
        }

        arsort($rows);

        return $rows;
    }

    /**
     * Beitrag des Bekanntmachungs-Radars: Wie viel des Vergabegeschäfts
     * überhaupt aus der Marktbeobachtung kommt.
     *
     * @return array{new: int, muted: int, converted: int}
     */
    private function radar(): array {
        $counts = TenderNoticeMatch::query()
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        return [
            'new' => (int) $counts->get(TenderNoticeMatch::STATE_NEW, 0),
            'muted' => (int) $counts->get(TenderNoticeMatch::STATE_MUTED, 0),
            'converted' => (int) $counts->get(TenderNoticeMatch::STATE_CONVERTED, 0),
        ];
    }

    /**
     * @param  array<string, array{count: int, value: float}> $pipeline
     * @return list<array{x: string, y: int}>
     */
    private function pipelineSeries(array $pipeline): array {
        $series = [];
        foreach ($pipeline as $status => $row) {
            $series[] = ['x' => (string) __("values.$status"), 'y' => $row['count']];
        }

        return $series;
    }

    /**
     * @param  array<string, array{count: int, value: float}> $windows
     * @return list<array{x: string, y: int}>
     */
    private function deadlineSeries(array $windows): array {
        $series = [];
        foreach ($windows as $key => $row) {
            if ($row['count'] > 0) {
                $series[] = ['x' => $this->windowLabel($key), 'y' => $row['count']];
            }
        }

        return $series;
    }

    /**
     * @param  list<array{name: string, open: int, soon: int, overdue: int, value: float}> $workload
     * @return list<array{x: string, y: int, y2: int}>
     */
    private function workloadSeries(array $workload): array {
        $series = [];
        foreach (array_slice($workload, 0, 15) as $row) {
            $series[] = ['x' => $row['name'], 'y' => $row['open'], 'y2' => $row['soon'] + $row['overdue']];
        }

        return $series;
    }

    /**
     * @param  array<string, int> $reasons
     * @return list<array{x: string, y: int}>
     */
    private function lossSeries(array $reasons): array {
        $series = [];
        foreach (array_slice($reasons, 0, 10, true) as $reason => $count) {
            $series[] = ['x' => $reason, 'y' => $count];
        }

        return $series;
    }

    public function windowLabel(string $key): string {
        return match ($key) {
            'overdue' => (string) __('Überfällig'),
            'week' => (string) __('Diese Woche'),
            'fortnight' => (string) __('In 2 Wochen'),
            'month' => (string) __('In 1 Monat'),
            'later' => (string) __('Später'),
            default => (string) __('Ohne Frist'),
        };
    }

    /**
     * @param array<string, array{count: int, value: float}>                          $pipeline
     * @param array{submitted: int, won: int, lost: int, withdrawn: int, win_rate: float|null, won_value: float, loss_reasons: array<string, int>} $decision
     * @param array<string, array{count: int, value: float}>                          $deadlines
     * @param list<array{name: string, open: int, soon: int, overdue: int, value: float}> $workload
     * @param array<string, int>                                                      $procedures
     */
    private function export(array $pipeline, array $decision, array $deadlines, array $workload, array $procedures, string $from, string $to, Request $request): Response {
        /** @var list<list<string|int|float|null>> $rows */
        $rows = [[
            (string) __('Block'), (string) __('Merkmal'), (string) __('Anzahl'), (string) __('Wert'), (string) __('Hinweis'),
        ]];

        foreach ($pipeline as $status => $row) {
            $rows[] = [(string) __('Pipeline'), (string) __("values.$status"), $row['count'], NumberHelper::toGermanFormat($row['value'], 2), ''];
        }
        foreach ($deadlines as $key => $row) {
            $rows[] = [(string) __('Fristfenster'), $this->windowLabel($key), $row['count'], NumberHelper::toGermanFormat($row['value'], 2), ''];
        }
        foreach ($workload as $row) {
            $rows[] = [(string) __('Fristenlast'), $row['name'], $row['open'], NumberHelper::toGermanFormat($row['value'], 2),
                (string) __(':soon fällig ≤ 14 Tage, :overdue überfällig', ['soon' => $row['soon'], 'overdue' => $row['overdue']])];
        }
        foreach ($procedures as $label => $count) {
            $rows[] = [(string) __('Verfahrensart'), $label, $count, '', ''];
        }
        foreach ($decision['loss_reasons'] as $reason => $count) {
            $rows[] = [(string) __('Verlustgrund'), $reason, $count, '', ''];
        }
        $rows[] = [(string) __('Entscheidung'), (string) __('Trefferquote'), '', $decision['win_rate'] !== null ? $decision['win_rate'] . ' %' : '—',
            (string) __(':won gewonnen, :lost verloren', ['won' => $decision['won'], 'lost' => $decision['lost']])];

        return $this->csvWithMetadata(
            $rows,
            'vergabe-cockpit_' . $from . '_' . $to . '.csv',
            'tender.cockpit',
            ['from' => $from, 'to' => $to],
            $request,
        );
    }
}
