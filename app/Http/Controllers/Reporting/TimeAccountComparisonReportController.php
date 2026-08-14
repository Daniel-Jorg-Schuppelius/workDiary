<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountComparisonReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{TimeAccount, User};
use App\Support\{Sqid, XlsxExport};
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Zeitkonten-Periodenvergleich (MVP-540, Q1 „Perioden-Vergleich"): Umsätze
 * eines Kontos je Mitarbeiter nebeneinander pro Kalenderwoche oder Monat,
 * flankiert von Anfangs- und Endstand. Bildschirm/CSV/PDF zeigen das gewählte
 * Konto; der Excel-Export liefert ALLE aktiven Konten als je ein Arbeitsblatt
 * derselben Arbeitsmappe (Q1 S. 114). Sichtweite wie die Zeitkonten-Auswertung:
 * Admin sieht alle, sonst nur die eigene Zeile.
 */
class TimeAccountComparisonReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use WritesReportCsv;

    /** Obergrenze dargestellter Perioden (ein Jahr in Wochen). */
    private const MAX_PERIODS = 53;

    public function index(Request $request): View|SymfonyResponse {
        /** @var User $viewer */
        $viewer = Auth::user();
        $isAdmin = $this->viewerIsAdmin();

        [$from, $to] = $this->resolveRange($request);
        $granularity = $request->query('granularity') === 'month' ? 'month' : 'week';
        $periods = $this->buildPeriods(CarbonImmutable::parse($from->toDateString()), CarbonImmutable::parse($to->toDateString()), $granularity);

        $accounts = TimeAccount::query()->where('is_active', true)->orderBy('name')->get();
        if ($accounts->isEmpty()) {
            return view('reports.time-account-comparison', [
                'accounts' => $accounts,
                'account' => null,
                'granularity' => $granularity,
                'periods' => $periods,
                'rows' => [],
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
        }

        $accountSqid = (string) $request->input('account', '');
        $account = $accountSqid !== ''
            ? $accounts->firstWhere('id', Sqid::decodeOrNumeric(TimeAccount::class, $accountSqid))
            : null;
        $account ??= $accounts->first();

        $usersQuery = User::query()
            ->where('organization_id', $viewer->organization_id)
            ->orderBy('name');
        if (! $isAdmin) {
            $usersQuery->whereKey($viewer->getKey());
        }
        $users = $usersQuery->get(['id', 'name']);

        $rows = $this->matrixFor($account, $users, $periods, $from->toDateString(), $to->toDateString(), $granularity);

        if ($request->query('export') === 'xlsx') {
            return $this->exportXlsx($accounts, $users, $periods, $from->toDateString(), $to->toDateString(), $granularity, $request);
        }
        if ($request->query('export') === 'csv') {
            return $this->exportCsv($account, $rows, $periods, $from->toDateString(), $to->toDateString(), $granularity, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($account, $rows, $periods, $from->toDateString(), $to->toDateString(), $granularity, $request);
        }

        return view('reports.time-account-comparison', [
            'accounts' => $accounts,
            'account' => $account,
            'accountSqid' => Sqid::encode(TimeAccount::class, (int) $account->getKey()),
            'granularity' => $granularity,
            'periods' => $periods,
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /**
     * Perioden-Raster zwischen from und to (inklusive), gedeckelt auf
     * {@see self::MAX_PERIODS}. Wochen laufen ISO-konform Mo–So.
     *
     * @return list<array{key: string, label: string, start: string, end: string}>
     */
    private function buildPeriods(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array {
        $periods = [];
        $cursor = $granularity === 'month' ? $from->startOfMonth() : $from->startOfWeek();
        while ($cursor->lessThanOrEqualTo($to) && count($periods) < self::MAX_PERIODS) {
            if ($granularity === 'month') {
                $periods[] = [
                    'key' => $cursor->format('Y-m'),
                    'label' => $cursor->isoFormat('MMM YYYY'),
                    'start' => $cursor->toDateString(),
                    'end' => $cursor->endOfMonth()->toDateString(),
                ];
                $cursor = $cursor->addMonth();
            } else {
                $periods[] = [
                    'key' => $cursor->format('o-\WW'),
                    'label' => (string) __('KW :week', ['week' => $cursor->isoWeek()]),
                    'start' => $cursor->toDateString(),
                    'end' => $cursor->endOfWeek()->toDateString(),
                ];
                $cursor = $cursor->addWeek();
            }
        }

        return $periods;
    }

    /**
     * Umsatz-Matrix eines Kontos: je Mitarbeiter Anfangsstand, Umsatz pro
     * Periode und Endstand. Nutzer ohne jede Bewegung werden ausgelassen.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @param  list<array{key: string, label: string, start: string, end: string}>  $periods
     * @return list<array{user: User, opening: float, byPeriod: array<string, float>, turnover: float, closing: float, tone: string}>
     */
    private function matrixFor(TimeAccount $account, $users, array $periods, string $from, string $to, string $granularity): array {
        $userIds = $users->pluck('id')->map(static fn ($v): int => (int) $v)->all();
        if ($userIds === []) {
            return [];
        }

        // Aggregate über den Query-Builder (kein Eloquent-selectRaw, vgl.
        // TimeAccountPostingService::rebuildBalances / PHPStan).
        $openings = DB::table('time_account_entries')
            ->where('time_account_id', $account->getKey())
            ->whereIn('user_id', $userIds)
            ->whereDate('booking_date', '<', $from)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(quantity) AS total')
            ->pluck('total', 'user_id');

        $daily = DB::table('time_account_entries')
            ->where('time_account_id', $account->getKey())
            ->whereIn('user_id', $userIds)
            ->whereDate('booking_date', '>=', $from)
            ->whereDate('booking_date', '<=', $to)
            ->groupBy('user_id', 'booking_date')
            ->selectRaw('user_id, booking_date, SUM(quantity) AS total')
            ->get();

        // Tagessummen in Perioden-Eimer kippen (SQLite liefert Datetime-Strings,
        // MariaDB DATE — beides über Carbon normalisiert).
        /** @var array<int, array<string, float>> $byUserPeriod */
        $byUserPeriod = [];
        foreach ($daily as $entry) {
            $date = CarbonImmutable::parse((string) $entry->booking_date);
            $key = $granularity === 'month' ? $date->format('Y-m') : $date->format('o-\WW');
            $uid = (int) $entry->user_id;
            $byUserPeriod[$uid][$key] = ($byUserPeriod[$uid][$key] ?? 0.0) + (float) $entry->total;
        }

        $rows = [];
        foreach ($users as $user) {
            $uid = (int) $user->getKey();
            $opening = (float) ($openings[$uid] ?? 0.0);
            $byPeriod = [];
            $turnover = 0.0;
            foreach ($periods as $period) {
                $value = (float) ($byUserPeriod[$uid][$period['key']] ?? 0.0);
                $byPeriod[$period['key']] = $value;
                $turnover += $value;
            }
            if ($opening === 0.0 && $turnover === 0.0) {
                continue;
            }
            $closing = $opening + $turnover;
            $rows[] = [
                'user' => $user,
                'opening' => $opening,
                'byPeriod' => $byPeriod,
                'turnover' => $turnover,
                'closing' => $closing,
                'tone' => $account->tone($closing),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{key: string, label: string, start: string, end: string}>  $periods
     * @return list<string>
     */
    private function headerRow(array $periods): array {
        return array_merge(
            [(string) __('Mitarbeiter'), (string) __('Anfangsstand')],
            array_map(static fn (array $p): string => $p['label'], $periods),
            [(string) __('Umsatz'), (string) __('Endstand')],
        );
    }

    /**
     * @param  list<array{user: User, opening: float, byPeriod: array<string, float>, turnover: float, closing: float, tone: string}>  $rows
     * @return list<list<int|float|string|null>>
     */
    private function bodyRows(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = array_merge(
                [(string) $row['user']->name, round($row['opening'], 2)],
                array_map(static fn (float $v): float => round($v, 2), array_values($row['byPeriod'])),
                [round($row['turnover'], 2), round($row['closing'], 2)],
            );
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function filterMeta(?TimeAccount $account, string $from, string $to, string $granularity): array {
        return [
            'account' => $account?->code,
            'from' => $from,
            'to' => $to,
            'granularity' => $granularity,
        ];
    }

    /**
     * @param  list<array{user: User, opening: float, byPeriod: array<string, float>, turnover: float, closing: float, tone: string}>  $rows
     * @param  list<array{key: string, label: string, start: string, end: string}>  $periods
     */
    private function exportCsv(TimeAccount $account, array $rows, array $periods, string $from, string $to, string $granularity, Request $request): Response {
        $out = array_merge(
            [$this->headerRow($periods)],
            array_map(static fn (array $row): array => array_map(
                static fn ($v) => is_float($v) ? number_format($v, 2, '.', '') : $v,
                $row,
            ), $this->bodyRows($rows)),
        );

        return $this->csvWithMetadata(
            $out,
            sprintf('zeitkonto-periodenvergleich_%s_%s_%s.csv', $account->code, $from, $to),
            'time_account_comparison',
            $this->filterMeta($account, $from, $to, $granularity),
            $request,
        );
    }

    /**
     * Q1 S. 114: alle aktiven Konten als je ein Arbeitsblatt derselben Mappe.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TimeAccount>  $accounts
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @param  list<array{key: string, label: string, start: string, end: string}>  $periods
     */
    private function exportXlsx($accounts, $users, array $periods, string $from, string $to, string $granularity, Request $request): Response {
        $this->auditExport($request, 'time_account_comparison', 'xlsx', $this->filterMeta(null, $from, $to, $granularity));

        $sheets = [];
        foreach ($accounts as $account) {
            $rows = $this->matrixFor($account, $users, $periods, $from, $to, $granularity);
            $sheets[] = [
                'title' => (string) $account->name,
                'headers' => $this->headerRow($periods),
                'rows' => $this->bodyRows($rows),
            ];
        }

        return response(XlsxExport::toStringMultiSheet($sheets), 200, [
            'Content-Type' => XlsxExport::MIME,
            'Content-Disposition' => 'attachment; filename="' . sprintf('zeitkonto-periodenvergleich_%s_%s.xlsx', $from, $to) . '"',
        ]);
    }

    /**
     * @param  list<array{user: User, opening: float, byPeriod: array<string, float>, turnover: float, closing: float, tone: string}>  $rows
     * @param  list<array{key: string, label: string, start: string, end: string}>  $periods
     */
    private function exportPdf(TimeAccount $account, array $rows, array $periods, string $from, string $to, string $granularity, Request $request): SymfonyResponse {
        return $this->pdfDownload('reports.pdf.time-account-comparison', [
            'account' => $account,
            'rows' => $rows,
            'periods' => $periods,
            'from' => $from,
            'to' => $to,
            'granularity' => $granularity,
        ], sprintf('zeitkonto-periodenvergleich_%s_%s_%s.pdf', $account->code, $from, $to), 'landscape', $request, 'time_account_comparison', $this->filterMeta($account, $from, $to, $granularity));
    }
}
