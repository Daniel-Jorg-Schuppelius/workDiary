<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{TimeAccount, TimeAccountEntry, User};
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Zeitkonten-Auswertung (MVP-526, Q1 „Zeitkonten"): je Mitarbeiter
 * Anfangsstand, Umsatz und Endstand eines gewählten Kontos im Zeitraum.
 * Admin sieht alle, sonst nur die eigene Zeile. CSV/PDF.
 */
class TimeAccountsReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        /** @var User $viewer */
        $viewer = Auth::user();
        $isAdmin = $this->viewerIsAdmin();

        [$from, $to] = $this->resolveRange($request);

        $accounts = TimeAccount::query()->where('is_active', true)->orderBy('name')->get();
        if ($accounts->isEmpty()) {
            return view('reports.time-accounts', [
                'accounts' => $accounts,
                'account' => null,
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

        // Eine Aggregation je Konto statt zwei Summen je Mitarbeiter (Vollscan
        // 2026-08-23, A10): booking_date ist DATE, daher Bereichsvergleich ohne
        // DATE()-Wrapper (Index bleibt nutzbar).
        $sums = TimeAccountEntry::query()
            ->where('time_account_id', $account->getKey())
            ->whereIn('user_id', $users->pluck('id'))
            ->where('booking_date', '<=', $to->toDateString())
            ->selectRaw('user_id, SUM(CASE WHEN booking_date < ? THEN quantity ELSE 0 END) AS opening, SUM(CASE WHEN booking_date >= ? THEN quantity ELSE 0 END) AS turnover', [$from->toDateString(), $from->toDateString()])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $rows = [];
        foreach ($users as $user) {
            $sum = $sums->get($user->getKey());
            $opening = (float) ($sum->opening ?? 0);
            $turnover = (float) ($sum->turnover ?? 0);
            if ($opening === 0.0 && $turnover === 0.0) {
                continue;
            }
            $closing = $opening + $turnover;
            $rows[] = [
                'user' => $user,
                'opening' => $opening,
                'turnover' => $turnover,
                'closing' => $closing,
                'tone' => $account->tone($closing),
            ];
        }

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($account, $rows, $from->toDateString(), $to->toDateString(), $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($account, $rows, $from->toDateString(), $to->toDateString(), $request);
        }

        return view('reports.time-accounts', [
            'accounts' => $accounts,
            'account' => $account,
            'accountSqid' => Sqid::encode(TimeAccount::class, (int) $account->getKey()),
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /** @param list<array{user: User, opening: float, turnover: float, closing: float, tone: string}> $rows */
    private function exportCsv(TimeAccount $account, array $rows, string $from, string $to, Request $request): Response {
        $out = [[
            (string) __('Mitarbeiter'),
            (string) __('Anfangsstand'),
            (string) __('Umsatz'),
            (string) __('Endstand'),
        ]];
        foreach ($rows as $row) {
            $out[] = [
                $row['user']->name,
                number_format($row['opening'], 2, '.', ''),
                number_format($row['turnover'], 2, '.', ''),
                number_format($row['closing'], 2, '.', ''),
            ];
        }

        return $this->csvWithMetadata($out, sprintf('zeitkonto_%s_%s_%s.csv', $account->code, $from, $to), 'time_accounts', [
            'account' => $account->code,
            'from' => $from,
            'to' => $to,
        ], $request);
    }

    /** @param list<array{user: User, opening: float, turnover: float, closing: float, tone: string}> $rows */
    private function exportPdf(TimeAccount $account, array $rows, string $from, string $to, Request $request): SymfonyResponse {
        return $this->pdfDownload('reports.pdf.time-accounts', [
            'account' => $account,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ], sprintf('zeitkonto_%s_%s_%s.pdf', $account->code, $from, $to), request: $request, reportCode: 'time_accounts', filters: [
            'account' => $account->code,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
