<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InsightsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\CustomerPortal\PortalInsights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Auswertungen im Kundenportal (Phase 154, MVP-1242–1244): eigene
 * Bereichsfreigabe `insights` (Routen-Gate); jeder Abschnitt erscheint nur,
 * wenn sein Bereich ebenfalls freigegeben ist ({@see PortalInsights}).
 */
class InsightsController extends Controller {
    public function index(Request $request, PortalInsights $insights): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $customer = $user->customer;
        abort_if($customer === null, 404);

        $years = $insights->years($customer);
        $year = PortalInsights::pickYear((int) $request->query('year', 0), $years);

        return view('customer.insights.index', [
            'customer' => $customer,
            'year' => $year,
            'years' => $years === [] ? [$year] : $years,
            'time' => $insights->time($customer, $year),
            'invoices' => $insights->invoices($customer, $year),
            'billing' => $insights->billing($customer, $year),
            'tickets' => $insights->tickets($customer, $year),
            'computedAt' => now(),
        ]);
    }
}
