<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalPortalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Rental\{RentalCase, RentalHandoverReport};
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Portal-Sicht des Verleihs (Feature 073, MVP-263/269): Kunden sehen NUR
 * eigene Verleihvorgänge (strikte customer_id-Prüfung, kein Admin-Bypass
 * über den customer-Guard) — ohne interne Kosten-, Risiko- oder
 * Wartungsnotizen — und bestätigen Übergaben.
 */
class RentalPortalController extends Controller {
    public function index(): View {
        $user = Auth::guard('customer')->user();
        abort_unless($user !== null && $user->customer_id !== null, 403);

        return view('customer.rental.index', [
            'cases' => RentalCase::query()
                ->where('customer_id', $user->customer_id)
                ->with(['caseAssets.asset'])
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function show(RentalCase $rental): View {
        $user = Auth::guard('customer')->user();
        abort_unless($user !== null && $user->customer_id !== null, 403);
        abort_unless((int) $rental->customer_id === (int) $user->customer_id, 404);

        $rental->load(['caseAssets.asset', 'handoverReports.accessoryItems', 'returnReports']);

        return view('customer.rental.show', ['case' => $rental]);
    }

    /** Portalbestätigung der Übergabe (MVP-263). */
    public function confirm(RentalCase $rental, RentalHandoverReport $report): RedirectResponse {
        $user = Auth::guard('customer')->user();
        abort_unless($user !== null && $user->customer_id !== null, 403);
        abort_unless((int) $rental->customer_id === (int) $user->customer_id, 404);
        abort_unless((int) $report->rental_case_id === (int) $rental->id, 404);

        if ($report->portal_confirmed_at === null) {
            $report->forceFill(['portal_confirmed_at' => now()])->save();
            $rental->audit('rental.handoverConfirmedByCustomer', ['report_id' => $report->id]);
        }

        return back()->with('status', __('Übergabe bestätigt.'));
    }
}
