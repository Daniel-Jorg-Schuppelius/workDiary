<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeCustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Plugins;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class LexofficeCustomerController extends Controller {
    public function __construct(
        private readonly PluginManager $manager,
    ) {
    }

    /**
     * Push the customer to Lexoffice as a contact (idempotent: updates the
     * existing external_reference if one is already known).
     */
    public function pushContact(Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        $plugin = $this->plugin();
        if ($plugin === null) {
            return back()->with('error', __('Lexoffice ist nicht aktiviert.'));
        }

        try {
            $externalId = $plugin->pushContact($customer);

            return back()->with('success', __('Kunde an Lexoffice übertragen (ID :id).', ['id' => $externalId]));
        } catch (Throwable $e) {
            Log::error('Lexoffice contact push failed', ['customer' => $customer->id, 'message' => $e->getMessage()]);

            return back()->with('error', __('Übertragung fehlgeschlagen: :msg', ['msg' => $e->getMessage()]));
        }
    }

    /**
     * Export the customer's billable, not-yet-exported time entries in the
     * given date range as a Lexoffice voucher.
     */
    public function exportTime(Request $request, Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $plugin = $this->plugin();
        if ($plugin === null) {
            return back()->with('error', __('Lexoffice ist nicht aktiviert.'));
        }

        try {
            $result = $plugin->exportCustomerTime(
                $customer,
                CarbonImmutable::parse($data['from'])->startOfDay(),
                CarbonImmutable::parse($data['to'])->endOfDay(),
            );

            if ($result['external_id'] === '') {
                return back()->with('info', __('Keine abrechenbaren, noch nicht übertragenen Zeiten im Zeitraum.'));
            }

            return back()->with('success', __('Beleg in Lexoffice angelegt (ID :id).', ['id' => $result['external_id']]));
        } catch (Throwable $e) {
            Log::error('Lexoffice time export failed', ['customer' => $customer->id, 'message' => $e->getMessage()]);

            return back()->with('error', __('Übertragung fehlgeschlagen: :msg', ['msg' => $e->getMessage()]));
        }
    }

    private function plugin(): ?LexofficePlugin {
        $plugin = $this->manager->withCapability(PluginCapability::TIME_EXPORT)->get(LexofficePlugin::ID);

        return $plugin instanceof LexofficePlugin ? $plugin : null;
    }
}
