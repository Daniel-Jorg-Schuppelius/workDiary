<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditActivityReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Audit-Aktivitätsbericht: Aggregation der AuditLogs nach Event, User und Auditable-Typ.
 * Nur für Administratoren.
 */
class AuditActivityReportController extends Controller {
    use ResolvesGlobalDateRange;

    private const RECENT_LIMIT = 100;

    public function index(Request $request): View|SymfonyResponse {
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        abort_unless($isAdmin, 403);

        $range = $this->globalDateRange();
        $from = Carbon::parse($range['from']->toDateString())->startOfDay();
        $to = Carbon::parse($range['to']->toDateString())->endOfDay();

        $base = AuditLog::query()->whereBetween('created_at', [$from, $to]);

        /** @var array<string, int> $byEvent */
        $byEvent = (clone $base)
            ->selectRaw('event, COUNT(*) as c')
            ->groupBy('event')
            ->orderByDesc('c')
            ->pluck('c', 'event')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var array<string, int> $byType */
        $byType = (clone $base)
            ->selectRaw('auditable_type, COUNT(*) as c')
            ->groupBy('auditable_type')
            ->orderByDesc('c')
            ->limit(20)
            ->pluck('c', 'auditable_type')
            ->map(static fn($v): int => (int) $v)
            ->all();

        $byUserRaw = (clone $base)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as c')
            ->groupBy('user_id')
            ->orderByDesc('c')
            ->limit(20)
            ->pluck('c', 'user_id')
            ->all();

        /** @var array<int, int> $byUserCounts */
        $byUserCounts = [];
        foreach ($byUserRaw as $uid => $c) {
            $byUserCounts[(int) $uid] = (int) $c;
        }

        /** @var Collection<int, User> $userModels */
        $userModels = $byUserCounts === []
            ? new Collection
            : User::query()->whereIn('id', array_keys($byUserCounts))->get(['id', 'name']);
        /** @var array<int, User> $usersById */
        $usersById = [];
        foreach ($userModels as $u) {
            $usersById[(int) $u->id] = $u;
        }

        /** @var array<int, array{user: ?User, count:int}> $byUser */
        $byUser = [];
        foreach ($byUserCounts as $uid => $c) {
            $byUser[$uid] = ['user' => $usersById[$uid] ?? null, 'count' => $c];
        }

        $total = (int) (clone $base)->count();
        $distinctUsers = count($byUserCounts);
        $distinctTypes = count($byType);

        /** @var Collection<int, AuditLog> $recent */
        $recent = (clone $base)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'ip', 'created_at']);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($byEvent, $byType, $byUser, $recent, $from->toDateString(), $to->toDateString());
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($byEvent, $byType, $byUser, $recent, [
                'total' => $total,
                'users' => $distinctUsers,
                'types' => $distinctTypes,
            ], $from->toDateString(), $to->toDateString());
        }

        return view('reports.audit-activity', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'byEvent' => $byEvent,
            'byType' => $byType,
            'byUser' => $byUser,
            'recent' => $recent,
            'totals' => [
                'total' => $total,
                'users' => $distinctUsers,
                'types' => $distinctTypes,
            ],
        ]);
    }

    /**
     * @param  array<string, int>  $byEvent
     * @param  array<string, int>  $byType
     * @param  array<int, array{user: ?User, count:int}>  $byUser
     * @param  Collection<int, AuditLog>  $recent
     */
    private function exportCsv(array $byEvent, array $byType, array $byUser, $recent, string $from, string $to): Response {
        $filename = sprintf('audit_%s_%s.csv', $from, $to);
        $rows = [];
        $rows[] = ['Bereich', 'Schlüssel', 'Anzahl'];
        foreach ($byEvent as $ev => $c) {
            $rows[] = ['Event', $ev, $c];
        }
        foreach ($byType as $t => $c) {
            $rows[] = ['Typ', $this->shortType($t), $c];
        }
        foreach ($byUser as $u) {
            $rows[] = ['User', $u['user'] !== null ? $u['user']->name : '—', $u['count']];
        }
        $rows[] = [];
        $rows[] = ['Zeitpunkt', 'User', 'Event', 'Typ', 'ID', 'IP'];
        foreach ($recent as $log) {
            $rows[] = [
                $log->created_at?->format('Y-m-d H:i:s') ?? '',
                $log->user !== null ? $log->user->name : '—',
                $log->event,
                $this->shortType((string) $log->auditable_type),
                (string) $log->auditable_id,
                (string) ($log->ip ?? ''),
            ];
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }

                return $s;
            }, $row)) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param  array<string, int>  $byEvent
     * @param  array<string, int>  $byType
     * @param  array<int, array{user: ?User, count:int}>  $byUser
     * @param  Collection<int, AuditLog>  $recent
     * @param  array{total:int, users:int, types:int}  $totals
     */
    private function exportPdf(array $byEvent, array $byType, array $byUser, $recent, array $totals, string $from, string $to): SymfonyResponse {
        $filename = sprintf('audit_%s_%s.pdf', $from, $to);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.audit-activity', [
            'byEvent' => $byEvent,
            'byType' => $byType,
            'byUser' => $byUser,
            'recent' => $recent,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    private function shortType(string $fqcn): string {
        $parts = explode('\\', $fqcn);

        return (string) end($parts);
    }
}
