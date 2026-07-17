<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsOpenIssueDrilldown.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting\Concerns;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Models\{DiaryEntry, OpenIssue, Protocol};
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * C20: Gemeinsames Gerüst der Drilldown-Controller (Customer/EntryType/Asset)
 * für offene Punkte und Defektprotokolle. Fachlich verschieden ist nur die
 * Subjekt-Eingrenzung — sie kommt als Closure auf den Query-Builder.
 */
trait BuildsOpenIssueDrilldown {
    /**
     * Offene-Punkte-Query: offene Status, optional nur eskalierte (Blocked),
     * Subjekt-Eingrenzung via Hook, neueste zuerst.
     *
     * @param  Closure(Builder<OpenIssue>): void  $applySubject
     * @return Builder<OpenIssue>
     */
    protected function openIssueDrilldownQuery(bool $escalatedOnly, Closure $applySubject): Builder {
        $query = OpenIssue::query()
            ->with(['assignee:id,name'])
            ->whereIn('status', OpenIssueStatus::openValues())
            ->when($escalatedOnly, fn($q) => $q->where('status', OpenIssueStatus::Blocked->value));

        $applySubject($query);

        return $query->orderByDesc('updated_at');
    }

    /**
     * Defektprotokoll-Query über DiaryEntry-Subjekte im Zeitraum;
     * leere Entry-Menge liefert bewusst eine leere Ergebnismenge (1=0).
     *
     * @param  array<int, int>  $entryIds
     * @return Builder<Protocol>
     */
    protected function defectProtocolDrilldownQuery(array $entryIds, CarbonImmutable $from, CarbonImmutable $to): Builder {
        return Protocol::query()
            ->with(['creator:id,name'])
            ->where('type', ProtocolType::Defect->value)
            ->where('subject_type', DiaryEntry::class)
            ->whereBetween('occurred_at', [$from, $to])
            ->when($entryIds !== [], fn($q) => $q->whereIn('subject_id', $entryIds), fn($q) => $q->whereRaw('1=0'))
            ->orderByDesc('occurred_at');
    }

    /**
     * CSV-Zeilen (inkl. Header) für offene Punkte; $subjectIdHeader schiebt
     * eine Subjekt-ID-Spalte nach der ID ein (Asset-Variante).
     *
     * @param  list<OpenIssue>  $issues
     * @return list<list<string|int|null>>
     */
    protected function openIssueCsvRows(array $issues, ?string $subjectIdHeader = null): array {
        $rows = [];
        $rows[] = $subjectIdHeader !== null
            ? ['ID', $subjectIdHeader, 'Titel', 'Status', 'Severity', 'Fällig', 'Zugewiesen']
            : ['ID', 'Titel', 'Status', 'Severity', 'Fällig', 'Zugewiesen'];
        foreach ($issues as $issue) {
            $row = [$issue->id];
            if ($subjectIdHeader !== null) {
                $row[] = $issue->subject_id;
            }
            $row[] = $issue->title;
            $row[] = $issue->status->label();
            $row[] = $issue->severity->label();
            $row[] = $issue->due_at?->format('Y-m-d') ?? '';
            $row[] = $issue->assignee->name ?? '';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * CSV-Zeilen (inkl. Header) für Defektprotokolle.
     *
     * @param  list<Protocol>  $protocols
     * @return list<list<string|int|null>>
     */
    protected function protocolCsvRows(array $protocols): array {
        $rows = [];
        $rows[] = ['ID', 'Titel', 'Status', 'Typ', 'Zeitpunkt', 'ErstelltVon', 'AuftragID'];
        foreach ($protocols as $protocol) {
            $rows[] = [
                $protocol->id,
                $protocol->title,
                $protocol->status->label(),
                $protocol->type->label(),
                $protocol->occurred_at->format('Y-m-d H:i'),
                $protocol->creator->name ?? '',
                $protocol->subject_id,
            ];
        }

        return $rows;
    }
}
