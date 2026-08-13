<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{TimeAccount, TimeAccountBalance, TimeAccountEntry, User};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Mitarbeiter-Sicht auf die eigenen Zeitkonten (MVP-526): Stand mit
 * Ampel und Trend, Journal-Drilldown je Konto.
 */
class TimeAccountController extends Controller {
    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::user();

        $accounts = TimeAccount::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($accounts as $account) {
            $latest = TimeAccountBalance::query()
                ->where('time_account_id', $account->getKey())
                ->where('user_id', $user->getKey())
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();
            $balance = $latest !== null ? (float) $latest->balance : 0.0;

            // Trend: Ø-Monatsumsatz der letzten 3 bebuchten Monate.
            $avg = (float) TimeAccountBalance::query()
                ->where('time_account_id', $account->getKey())
                ->where('user_id', $user->getKey())
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->limit(3)
                ->get()
                ->avg('turnover');

            $rows[] = [
                'account' => $account,
                'sqid' => Sqid::encode(TimeAccount::class, (int) $account->getKey()),
                'balance' => $balance,
                'tone' => $account->tone($balance),
                'avg_turnover' => $avg,
                'projected' => $balance + 3 * $avg,
            ];
        }

        // Journal-Drilldown für ein gewähltes Konto.
        $detail = null;
        $entries = null;
        $detailSqid = (string) $request->input('account', '');
        if ($detailSqid !== '') {
            $detailId = Sqid::decodeOrNumeric(TimeAccount::class, $detailSqid);
            $detail = $accounts->firstWhere('id', $detailId);
            if ($detail !== null) {
                $entries = TimeAccountEntry::query()
                    ->where('time_account_id', $detail->getKey())
                    ->where('user_id', $user->getKey())
                    ->orderByDesc('booking_date')
                    ->orderByDesc('id')
                    ->paginate(25)
                    ->withQueryString();
            }
        }

        return view('time-accounts.index', [
            'rows' => $rows,
            'detail' => $detail,
            'entries' => $entries,
        ]);
    }
}
