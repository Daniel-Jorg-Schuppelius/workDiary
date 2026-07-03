<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Expense\ExpenseStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Expense, User};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Spesen-Report: Aggregation pro Mitarbeiter × Kategorie × Monat.
 * Bereich:
 *  - "mine": eigener User (Default)
 *  - "team": Org-weit (nur Admin)
 */
class ExpenseReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View {
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
            $scope = 'mine';
        }

        $range = $this->globalDateRange();
        $from = Carbon::parse($range['from']->toDateString())->startOfDay();
        $to = Carbon::parse($range['to']->toDateString())->endOfDay();

        $statusFilter = $request->string('status')->toString();
        $statusEnum = $statusFilter !== '' ? ExpenseStatus::tryFrom($statusFilter) : null;

        $query = Expense::query()
            ->with(['user:id,name', 'category:id,label,icon,color'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($scope === 'mine') {
            $query->where('user_id', Auth::id());
        } elseif ($authUser->organization_id !== null) {
            $query->where('organization_id', $authUser->organization_id);
        }

        if ($statusEnum !== null) {
            $query->where('status', $statusEnum->value);
        }

        /** @var Collection<int, Expense> $expenses */
        $expenses = $query->get();

        [$rows, $months, $totalsPerUser, $totalsPerCategory, $totalsPerMonth, $grandTotal] = $this->aggregate($expenses);

        return view('reports.expenses', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'statusFilter' => $statusFilter,
            'statusOptions' => ExpenseStatus::cases(),
            'rows' => $rows,
            'months' => $months,
            'totalsPerUser' => $totalsPerUser,
            'totalsPerCategory' => $totalsPerCategory,
            'totalsPerMonth' => $totalsPerMonth,
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return array{
     *     0: array<string, array{user:string, category:string, color:?string, icon:?string, months:array<string,float>, total:float}>,
     *     1: array<int, string>,
     *     2: array<string, float>,
     *     3: array<string, float>,
     *     4: array<string, float>,
     *     5: float
     * }
     */
    private function aggregate(Collection $expenses): array {
        $rows = [];
        $months = [];
        $totalsPerUser = [];
        $totalsPerCategory = [];
        $totalsPerMonth = [];
        $grandTotal = 0.0;

        foreach ($expenses as $expense) {
            $userName = $expense->user->name ?? '—';
            $categoryLabel = $expense->category->label ?? '—';
            $color = $expense->category?->color;
            $icon = $expense->category?->icon;
            $month = $expense->date->format('Y-m');
            $amount = (float) $expense->amount_gross;

            $rowKey = $userName . '||' . $categoryLabel;
            if (! isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'user' => $userName,
                    'category' => $categoryLabel,
                    'color' => $color,
                    'icon' => $icon,
                    'months' => [],
                    'total' => 0.0,
                ];
            }
            $rows[$rowKey]['months'][$month] = ($rows[$rowKey]['months'][$month] ?? 0.0) + $amount;
            $rows[$rowKey]['total'] += $amount;

            $totalsPerUser[$userName] = ($totalsPerUser[$userName] ?? 0.0) + $amount;
            $totalsPerCategory[$categoryLabel] = ($totalsPerCategory[$categoryLabel] ?? 0.0) + $amount;
            $totalsPerMonth[$month] = ($totalsPerMonth[$month] ?? 0.0) + $amount;
            $grandTotal += $amount;

            $months[$month] = true;
        }

        $monthKeys = array_keys($months);
        sort($monthKeys);

        uksort($rows, fn(string $a, string $b): int => strnatcasecmp($a, $b));

        return [$rows, $monthKeys, $totalsPerUser, $totalsPerCategory, $totalsPerMonth, $grandTotal];
    }
}
