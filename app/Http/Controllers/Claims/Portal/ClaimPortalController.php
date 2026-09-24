<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimPortalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Claims\ClaimCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * Portal-Sicht Reklamationen (Feature 072, MVP-256): Kunden sehen NUR
 * die eigenen Fälle (strikte customer_id-Prüfung, kein Admin-Bypass über
 * den customer-Guard) mit Status und können Nachreichungen ergänzen.
 * Interne Bewertung/Entscheidung/Beträge bleiben unsichtbar.
 */
class ClaimPortalController extends Controller {
    public function index(): View {
        $user = Auth::guard('customer')->user();
        abort_unless($user !== null && $user->customer_id !== null, 403);

        return view('customer.claims.index', [
            'cases' => ClaimCase::query()
                ->where('customer_id', $user->customer_id)
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function show(ClaimCase $claim): View {
        $user = Auth::guard('customer')->user();
        abort_unless($user !== null && $user->customer_id !== null, 403);
        abort_unless((int) $claim->customer_id === (int) $user->customer_id, 404);

        return view('customer.claims.show', [
            'claim' => $claim->load(['rmaReturns', 'actions']),
        ]);
    }

    /** Nachreichung: Notiz des Kunden landet als Nachweis am Fall. */
    public function addNote(Request $request, ClaimCase $claim): RedirectResponse {
        $user = Auth::guard('customer')->user();
        abort_unless($user !== null && $user->customer_id !== null, 403);
        abort_unless((int) $claim->customer_id === (int) $user->customer_id, 404);

        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);

        $claim->evidence()->create([
            'organization_id' => $claim->organization_id,
            'kind' => 'message',
            'title' => (string) __('Nachreichung aus dem Kundenportal'),
            'note' => $data['note'],
            'recorded_at' => now(),
        ]);

        return back()->with('status', __('Nachreichung übermittelt.'));
    }
}
