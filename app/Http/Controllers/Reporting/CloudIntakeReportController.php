<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\CloudIntake\CloudIntakeItemStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem};
use App\Models\User;
use App\Support\{CarbonFmt, ChartBucket};
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;

/**
 * Importbericht Cloud-Dokumenteingang (Feature 080 P9; Audit 2026-08, W4.4).
 *
 * Das Admin-Importprotokoll zeigt die letzten Einzelvorgänge einer Verbindung —
 * dieser Bericht beantwortet die andere Frage: Was ist im Zeitraum insgesamt
 * angekommen, was wurde abgewiesen, und wo klemmt es? Er ist der Grund, aus dem
 * ein abgewiesener Stapel auffällt, bevor jemand die fehlende Rechnung sucht.
 *
 * Bewusst rein lesend und ohne Datei-Inhalte: gezeigt werden Pfad, Status und
 * Grund — nie der Inhalt des importierten Belegs.
 */
class CloudIntakeReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    /** Zeilenobergrenze der Detailtabelle (Bericht, kein Protokollersatz). */
    private const ROW_LIMIT = 500;

    public function index(Request $request): View|Response {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can(Permission::ReportView->value), 403);

        [$from, $to] = $this->resolveRange($request);
        $data = $this->build($from, $to);

        if ($request->query('export') !== null) {
            return $this->export($request, $data['rows'], $from, $to);
        }

        return view('reports.cloud-intake', $data + [
            'label' => CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
        ]);
    }

    /**
     * @return array{
     *     total: int,
     *     byStatus: array<string, int>,
     *     perPeriod: list<array{x: string, y: int}>,
     *     byProvider: list<array{x: string, y: int}>,
     *     byReason: list<array{reason: string, count: int}>,
     *     connections: list<array{label: string, provider: string, imported: int, rejected: int, lastRun: string|null, status: string}>,
     *     rows: list<array{date: string, provider: string, path: string, status: string, reason: string}>,
     * }
     */
    private function build(CarbonImmutable $from, CarbonImmutable $to): array {
        // Der Org-Scope kommt aus BelongsToOrganization — hier bewusst KEIN
        // withoutGlobalScopes(): der Bericht zeigt nur die eigene Organisation.
        $items = CloudDocumentItem::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderByDesc('created_at')
            ->get(['id', 'connection_id', 'provider', 'source_path', 'status', 'status_reason', 'created_at']);

        $granularity = $this->bucketGranularity($from, $to);
        $buckets = $this->buildBucketsInRange($from, $to);

        /** @var array<string, int> $perBucket */
        $perBucket = [];
        foreach ($buckets as $bucket) {
            $perBucket[$bucket['key']] = 0;
        }

        /** @var array<string, int> $byStatus */
        $byStatus = [];
        /** @var array<string, int> $byProvider */
        $byProvider = [];
        /** @var array<string, int> $byReason */
        $byReason = [];
        /** @var array<int, array{imported: int, rejected: int}> $perConnection */
        $perConnection = [];
        $rows = [];

        foreach ($items as $item) {
            $status = $item->status->value;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            $providerLabel = $item->provider->label();
            $byProvider[$providerLabel] = ($byProvider[$providerLabel] ?? 0) + 1;

            $createdAt = $item->created_at;
            if ($createdAt !== null) {
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse($createdAt->toDateTimeString()))[0];
                if (array_key_exists($key, $perBucket)) {
                    $perBucket[$key]++;
                }
            }

            $connectionId = (int) $item->connection_id;
            $perConnection[$connectionId] ??= ['imported' => 0, 'rejected' => 0];
            if ($item->status === CloudIntakeItemStatus::Imported) {
                $perConnection[$connectionId]['imported']++;
            } elseif ($item->status === CloudIntakeItemStatus::Rejected) {
                $perConnection[$connectionId]['rejected']++;
                $reason = trim((string) $item->status_reason);
                $reason = $reason !== '' ? $reason : (string) __('Ohne Angabe');
                $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
            }

            if (count($rows) < self::ROW_LIMIT) {
                $rows[] = [
                    'date' => $createdAt?->format('d.m.Y H:i') ?? '—',
                    'provider' => $providerLabel,
                    'path' => (string) $item->source_path,
                    'status' => $item->status->label(),
                    'reason' => (string) ($item->status_reason ?? ''),
                ];
            }
        }

        arsort($byProvider);
        arsort($byReason);

        $perPeriod = [];
        foreach ($buckets as $bucket) {
            $perPeriod[] = ['x' => $bucket['shortLabel'], 'y' => $perBucket[$bucket['key']]];
        }

        $providerSeries = [];
        foreach ($byProvider as $name => $count) {
            $providerSeries[] = ['x' => (string) $name, 'y' => $count];
        }

        $reasonRows = [];
        foreach (array_slice($byReason, 0, 15, true) as $reason => $count) {
            $reasonRows[] = ['reason' => (string) $reason, 'count' => $count];
        }

        return [
            'total' => $items->count(),
            'byStatus' => $byStatus,
            'perPeriod' => $items->isEmpty() ? [] : $perPeriod, // Leerzustand statt Null-Serie
            'byProvider' => $providerSeries,
            'byReason' => $reasonRows,
            'connections' => $this->connectionRows($perConnection),
            'rows' => $rows,
        ];
    }

    /**
     * Verbindungszeilen inklusive derer OHNE Vorgänge im Zeitraum — gerade
     * eine Verbindung, die nichts geliefert hat, ist das interessante Signal.
     *
     * @param  array<int, array{imported: int, rejected: int}>  $perConnection
     * @return list<array{label: string, provider: string, imported: int, rejected: int, lastRun: string|null, status: string}>
     */
    private function connectionRows(array $perConnection): array {
        $rows = [];
        foreach (CloudDocumentConnection::query()->orderBy('provider')->get() as $connection) {
            $counts = $perConnection[(int) $connection->getKey()] ?? ['imported' => 0, 'rejected' => 0];
            $rows[] = [
                'label' => (string) ($connection->root_folder_path ?? $connection->container_label ?? '/'),
                'provider' => $connection->provider->label(),
                'imported' => $counts['imported'],
                'rejected' => $counts['rejected'],
                'lastRun' => $connection->last_run_at?->format('d.m.Y H:i'),
                'status' => $connection->status->label(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{date: string, provider: string, path: string, status: string, reason: string}>  $rows
     */
    private function export(Request $request, array $rows, CarbonImmutable $from, CarbonImmutable $to): Response {
        $csv = [[
            (string) __('cloud_intake.report.column.date'),
            (string) __('cloud_intake.report.column.provider'),
            (string) __('cloud_intake.report.column.path'),
            (string) __('cloud_intake.report.column.status'),
            (string) __('cloud_intake.report.column.reason'),
        ]];
        foreach ($rows as $row) {
            $csv[] = [$row['date'], $row['provider'], $row['path'], $row['status'], $row['reason']];
        }

        return $this->csvWithMetadata(
            $csv,
            'cloud-intake-' . $from->toDateString() . '_' . $to->toDateString() . '.csv',
            'cloud-intake',
            ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            $request,
        );
    }
}
