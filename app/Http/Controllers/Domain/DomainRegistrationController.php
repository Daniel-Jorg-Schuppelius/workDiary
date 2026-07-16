<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRegistrationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Domain;

use App\Enums\Domain\DomainProviderCommandStatus;
use App\Http\Controllers\Controller;
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Plugins\Support\Domain\DomainRateBudgetException;
use App\Services\Domain\{DomainActionException, DomainAvailabilityService, DomainRegistrationService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Verfügbarkeitsprüfung und kontrollierte Registrierung (Feature 083,
 * MVP-388). Die Prüfung ist budgetiert; die Registrierung erzwingt Preflight
 * (Kunde, Kontakte, Nameserver, Preisbestätigung) und läuft über die
 * Command-Outbox mit Reconciliation.
 */
class DomainRegistrationController extends Controller {
    public function check(Request $request, DomainAvailabilityService $service): RedirectResponse {
        Gate::authorize('register', DomainProjection::class);

        $data = $request->validate([
            'connection' => ['required', 'string'],
            'domains' => ['required', 'string', 'max:2000'],
        ]);
        $connection = $this->connection($data['connection']);
        $domains = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $data['domains']) ?: [])));

        try {
            $results = $service->check($connection, $domains);
        } catch (DomainRateBudgetException) {
            return back()->with('error', __('domain.errors.rate_budget'));
        }

        return back()->with('availability', $results)->with('availability_connection', $connection->sqid);
    }

    public function store(Request $request, DomainRegistrationService $service): RedirectResponse {
        Gate::authorize('register', DomainProjection::class);

        $data = $request->validate([
            'connection' => ['required', 'string'],
            'domain' => ['required', 'string', 'max:253'],
            'customer' => ['required', 'string'],
            'period' => ['nullable', 'integer', 'min:1', 'max:10'],
            'renewal_mode' => ['nullable', 'string'],
            'owner_contact' => ['required', 'string', 'max:190'],
            'admin_contact' => ['nullable', 'string', 'max:190'],
            'tech_contact' => ['nullable', 'string', 'max:190'],
            'billing_contact' => ['nullable', 'string', 'max:190'],
            'nameservers' => ['required', 'string', 'max:1000'],
            'cost_center' => ['nullable', 'string', 'max:64'],
            'price_confirmed' => ['accepted'],
        ]);

        $connection = $this->connection($data['connection']);
        $customer = (new \App\Models\Customer())->resolveRouteBinding($data['customer']);
        if ($customer === null) {
            return back()->with('error', __('domain.errors.customer_required'));
        }
        $customerId = $customer->getKey();
        $nameservers = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $data['nameservers']) ?: [])));

        try {
            $command = $service->register($connection, $data['domain'], [
                'customer_id' => $customerId,
                'period' => (int) ($data['period'] ?? 1),
                'renewal_mode' => $data['renewal_mode'] ?? null,
                'owner_contact' => $data['owner_contact'],
                'admin_contact' => $data['admin_contact'] ?? null,
                'tech_contact' => $data['tech_contact'] ?? null,
                'billing_contact' => $data['billing_contact'] ?? null,
                'nameservers' => $nameservers,
                'cost_center' => $data['cost_center'] ?? null,
                'price_confirmed' => true,
            ], ($request->user() ?? abort(401)));
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $command->status === DomainProviderCommandStatus::Confirmed ? 'success' : 'error',
            $command->status === DomainProviderCommandStatus::Confirmed
                ? __('domain.flash.registered')
                : __('domain.flash.command_' . $command->status->value),
        );
    }

    private function connection(string $sqid): DomainProviderConnection {
        $connection = (new DomainProviderConnection())->resolveRouteBinding($sqid);
        if (! $connection instanceof DomainProviderConnection) {
            abort(404);
        }

        return $connection;
    }
}
