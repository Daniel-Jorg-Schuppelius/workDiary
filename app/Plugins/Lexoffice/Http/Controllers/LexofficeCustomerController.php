<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeCustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Customer, ExternalReference, User};
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Log};
use Throwable;

class LexofficeCustomerController extends Controller {
    public function __construct(
        private readonly PluginManager $manager,
    ) {}

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

    /**
     * Push aller noch nicht synchronisierten Kunden zu Lexoffice (Bulk-Aktion
     * aus der Kunden-Listenansicht).
     */
    public function bulkPush(): RedirectResponse {
        Gate::authorize('viewAny', Customer::class);
        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser?->canManageBilling()) {
            abort(403);
        }

        $plugin = $this->plugin();
        if ($plugin === null) {
            return back()->with('error', __('Lexoffice-Plugin ist nicht aktiviert.'));
        }

        $alreadySyncedIds = ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', (new Customer)->getMorphClass())
            ->pluck('referenceable_id')
            ->all();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Customer> $candidates */
        $candidates = Customer::query()
            ->whereNull('archived_at')
            ->whereNotIn('id', $alreadySyncedIds)
            ->get();

        $ok = 0;
        $fail = 0;
        foreach ($candidates as $customer) {
            try {
                $plugin->pushContact($customer);
                $ok++;
            } catch (Throwable $e) {
                $fail++;
                report($e);
            }
        }

        $msg = __('Lexoffice-Sync: :ok übertragen, :fail Fehler.', ['ok' => $ok, 'fail' => $fail]);

        return back()->with($fail > 0 ? 'info' : 'success', $msg);
    }

    private function plugin(): ?LexofficePlugin {
        $plugin = $this->manager->withCapability(PluginCapability::TimeExport)->get(LexofficePlugin::ID);

        return $plugin instanceof LexofficePlugin ? $plugin : null;
    }
}
