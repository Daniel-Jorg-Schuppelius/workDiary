<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteWinRateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Sales\Quote;
use App\Services\Sales\QuoteWinRateReport;
use App\Support\CarbonFmt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Trefferquote der Angebote im globalen Zeitraum (Feature 112, MVP-998). */
class QuoteWinRateController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function index(Request $request, QuoteWinRateReport $report): View {
        Gate::authorize('viewAny', Quote::class);
        [$from, $to] = $this->resolveRange($request);
        $groupBy = in_array($request->query('group'), QuoteWinRateReport::GROUPS, true) ? (string) $request->query('group') : 'customer';

        return view('quotes.win-rate', [
            'result' => $report->build($this->currentOrganizationId(), $from, $to, $groupBy),
            'groupBy' => $groupBy,
            'label' => CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to),
        ]);
    }
}
