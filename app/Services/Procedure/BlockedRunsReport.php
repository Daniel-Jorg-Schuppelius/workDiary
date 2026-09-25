<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BlockedRunsReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Enums\Procedure\{ProcedureRunEventType, ProcedureRunStatus};
use App\Models\Procedure\{ProcedureRun, ProcedureRunEvent};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Bericht über blockierte Prozedurläufe (MVP-897): aktuell gesperrte Läufe
 * und die im Zeitraum beendeten Sperren je Sperrgrund und Vorlage mit Anzahl,
 * mittlerer und längster Dauer. Grundlage ist das Laufjournal (runUnblocked
 * trägt die Dauer).
 */
final class BlockedRunsReport {
    public function __construct(private readonly ProcedureRunBlockTracker $tracker) {}

    /** @return list<array{run: ProcedureRun, template: string, reason: string, since: CarbonImmutable, hours: float}> */
    public function current(): array {
        $this->tracker->releaseElapsedWaits();
        $now = CarbonImmutable::now();

        return array_values(ProcedureRun::query()
            ->with('templateVersion.template')
            ->where('status', ProcedureRunStatus::Blocked->value)
            ->whereNotNull('blocked_at')
            ->orderBy('blocked_at')
            ->get()
            ->map(static fn (ProcedureRun $run): array => [
                'run' => $run,
                'template' => (string) ($run->templateVersion?->template->name ?? '—'),
                'reason' => (string) $run->blocked_reason,
                'since' => CarbonImmutable::parse($run->blocked_at),
                'hours' => round(CarbonImmutable::parse($run->blocked_at)->diffInSeconds($now) / 3600, 1),
            ])
            ->all());
    }

    /** @return list<array{reason: string, template: string, count: int, avg_hours: float, max_hours: float}> */
    public function periods(CarbonImmutable $from, CarbonImmutable $to): array {
        $events = ProcedureRunEvent::query()
            ->with('run.templateVersion.template')
            ->where('event_type', ProcedureRunEventType::RunUnblocked->value)
            ->where('created_at', '>=', $from->startOfDay())
            ->where('created_at', '<', DateRange::dayAfter($to))
            ->whereHas('run')
            ->get();

        $groups = [];
        foreach ($events as $event) {
            $reason = (string) data_get($event->payload, 'reason', '');
            $template = (string) ($event->run?->templateVersion?->template->name ?? '—');
            $key = $reason . "\0" . $template;
            $groups[$key] ??= ['reason' => $reason, 'template' => $template, 'seconds' => []];
            $groups[$key]['seconds'][] = (int) data_get($event->payload, 'seconds', 0);
        }

        $rows = array_map(static fn (array $g): array => [
            'reason' => $g['reason'],
            'template' => $g['template'],
            'count' => count($g['seconds']),
            'avg_hours' => round(array_sum($g['seconds']) / count($g['seconds']) / 3600, 1),
            'max_hours' => round(max($g['seconds']) / 3600, 1),
        ], array_values($groups));
        usort($rows, static fn (array $a, array $b): int => [$b['count'], $a['template']] <=> [$a['count'], $b['template']]);

        return $rows;
    }
}
