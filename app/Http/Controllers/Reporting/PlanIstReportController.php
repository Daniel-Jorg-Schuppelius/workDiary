<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Reporting\PlanIstReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Plan/Ist-Anwesenheits-Report — persönliche Sicht (MVP-018).
 *
 * Team-/Org-Sichten (mit Permissions `report.presence.team`/.organization)
 * folgen in einem späteren Schritt; siehe docs/plan-ist-abgleich.md §4.2.
 */
class PlanIstReportController extends Controller {
    public function __construct(private readonly PlanIstReportBuilder $builder) {}

    public function presence(Request $request): View {
        /** @var User $user */
        $user = Auth::user();

        $now = CarbonImmutable::now();
        $from = $request->filled('from')
            ? CarbonImmutable::parse((string) $request->input('from'))
            : $now->startOfMonth();
        $to = $request->filled('to')
            ? CarbonImmutable::parse((string) $request->input('to'))
            : $now->endOfMonth();

        $rows = $this->builder->presenceFor($user, $from, $to);

        $totals = [
            'plan_minutes' => array_sum(array_column($rows, 'plan_minutes')),
            'actual_minutes' => array_sum(array_column($rows, 'actual_minutes')),
            'delta_minutes' => array_sum(array_column($rows, 'delta_minutes')),
            'warnings' => array_sum(array_map(fn($r) => count($r['warnings']), $rows)),
        ];

        return view('reports.plan-ist.presence', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
