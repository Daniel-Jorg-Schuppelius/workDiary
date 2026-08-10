<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalVisibilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{Customer, User};
use App\Services\CustomerPortal\PortalVisibility;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Konfiguration der sichtbaren Portalbereiche und der Zeit-Detailstufe je
 * Kunde (MVP-511). Änderungen werden mit Akteur, alter und neuer Freigabe
 * auditiert und wirken unmittelbar (Entscheidung je Request).
 */
class CustomerPortalVisibilityController extends Controller {
    public function update(Customer $customer, Request $request, PortalVisibility $visibility): RedirectResponse {
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->isAdmin() || Gate::allows(Permission::CustomerPortalVisibilityManage->value)), 403);
        Gate::authorize('view', $customer);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:64'],
            'time_detail' => ['required', 'string', 'in:none,summary,entries,entries_with_description'],
            'time_scope' => ['required', 'string', 'in:published,all'],
        ]);

        $visibility->update($customer, [
            'enabled' => (bool) $data['enabled'],
            'capabilities' => (array) ($data['capabilities'] ?? []),
            'time_detail' => (string) $data['time_detail'],
            'time_scope' => (string) $data['time_scope'],
        ], $user);

        return redirect()->route('customers.show', ['customer' => $customer, '#' => 'portal-visibility'])
            ->with('success', __('Portal-Sichtbarkeiten gespeichert.'));
    }
}
