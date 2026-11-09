<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportCustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\RequiresLegacyAdmin;
use App\Legacy\Http\Requests\SaveLegacySupportCustomerRequest;
use App\Legacy\Models\LegacySupportCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Kundenverwaltung des Support-Portals (Zugänge der Kunden in `kp_user`). */
class LegacySupportCustomerController extends Controller {
    use RequiresLegacyAdmin;

    public function index(): View {
        $this->ensureAdmin();

        return view('legacy.support.customers.index', [
            'customers' => LegacySupportCustomer::query()->customers()
                ->withCount(['orders', 'archivedOrders'])
                ->orderBy('kunde')
                ->get(['id', 'kunde', 'usern']),
            'writeEnabled' => (bool) config('app.legacy_write_enabled'),
        ]);
    }

    public function create(): View {
        $this->ensureAdmin();

        return view('legacy.support.customers._form_dialog', ['customer' => null, 'isEdit' => false]);
    }

    public function store(SaveLegacySupportCustomerRequest $request): RedirectResponse {
        $this->ensureAdmin();
        $data = $request->validated();

        LegacySupportCustomer::query()->create([
            'kunde' => (string) $data['kunde'],
            'usern' => (string) $data['usern'],
            'passw' => (string) $data['passw'],
        ]);

        return redirect()->route('legacy.support.customers.index')
            ->with('success', __('legacy_support.flash.customer_created', ['name' => $data['kunde']]));
    }

    public function edit(LegacySupportCustomer $customer): View {
        $this->ensureAdmin();
        $this->ensureCustomerAccount($customer);

        return view('legacy.support.customers._form_dialog', ['customer' => $customer, 'isEdit' => true]);
    }

    public function update(SaveLegacySupportCustomerRequest $request, LegacySupportCustomer $customer): RedirectResponse {
        $this->ensureAdmin();
        $this->ensureCustomerAccount($customer);
        $data = $request->validated();

        $customer->fill(['kunde' => (string) $data['kunde'], 'usern' => (string) $data['usern']]);
        if (filled($data['passw'] ?? null)) {
            $customer->passw = (string) $data['passw'];
        }
        $customer->save();

        return redirect()->route('legacy.support.customers.index')
            ->with('success', __('legacy_support.flash.customer_updated', ['name' => $customer->kunde]));
    }

    public function destroy(LegacySupportCustomer $customer): RedirectResponse {
        $this->ensureAdmin();
        $this->ensureCustomerAccount($customer);

        // Das Altportal löschte ohne Prüfung; laufende Aufträge verloren damit ihren Kunden.
        if ($customer->orders()->exists()) {
            return back()->with('error', __('legacy_support.flash.customer_has_orders', ['name' => $customer->kunde]));
        }

        $name = $customer->kunde;
        $customer->delete();

        return redirect()->route('legacy.support.customers.index')
            ->with('success', __('legacy_support.flash.customer_deleted', ['name' => $name]));
    }

    /** Die Bürokonten des Altportals sind hier nicht zu verwalten. */
    private function ensureCustomerAccount(LegacySupportCustomer $customer): void {
        abort_unless($customer->isCustomer(), 404);
    }
}
