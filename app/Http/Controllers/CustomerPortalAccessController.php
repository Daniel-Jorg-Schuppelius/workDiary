<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalAccessController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{Customer, User};
use App\Services\CustomerPortal\PortalAccessService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Interne Verwaltung der Kundenportal-Zugänge an der Kundenakte (MVP-510):
 * einladen, erneut senden, deaktivieren/widerrufen, reaktivieren. Alle
 * Aktionen strikt organisations- und kundenbezogen; eigene Permission
 * `customerPortal.access.manage` statt eines impliziten update-Bypasses.
 */
class CustomerPortalAccessController extends Controller {
    public function __construct(private readonly PortalAccessService $service) {}

    /** Einladungs-Dialog (Modal-Fragment). */
    public function createDialog(Customer $customer): View {
        $this->authorizeManage($customer);

        return view('customers._portal_invite_dialog', [
            'customer' => $customer,
            'isDialog' => true,
        ]);
    }

    public function store(Customer $customer, Request $request): RedirectResponse {
        $this->authorizeManage($customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->invite($customer, $data['name'], $data['email'], $actor);

        return redirect()->route('customers.show', ['customer' => $customer, '#' => 'portal-access'])
            ->with('success', __('Einladung an :email versendet.', ['email' => mb_strtolower(trim((string) $data['email']))]));
    }

    public function resend(Customer $customer, User $portalUser): RedirectResponse {
        $this->authorizeManage($customer);
        $this->assertBelongsToCustomer($customer, $portalUser);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->resend($portalUser, $actor);

        return back()->with('success', __('Einladung an :email erneut versendet.', ['email' => $portalUser->email]));
    }

    public function deactivate(Customer $customer, User $portalUser): RedirectResponse {
        $this->authorizeManage($customer);
        $this->assertBelongsToCustomer($customer, $portalUser);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deactivate($portalUser, $actor);

        return back()->with('success', __('Portalzugang deaktiviert — bestehende Sitzungen wurden beendet.'));
    }

    public function reactivate(Customer $customer, User $portalUser): RedirectResponse {
        $this->authorizeManage($customer);
        $this->assertBelongsToCustomer($customer, $portalUser);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->reactivate($portalUser, $actor);

        return back()->with('success', __('Portalzugang reaktiviert.'));
    }

    private function authorizeManage(Customer $customer): void {
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->isAdmin() || Gate::allows(Permission::CustomerPortalAccessManage->value)), 403);
        // Kundenbezug: Sicht auf die Kundenakte ist Mindestvoraussetzung;
        // der Org-Scope der Route-Bindung greift über die CustomerPolicy.
        Gate::authorize('view', $customer);
    }

    /** Fremde Organisationen, fremde Kunden und interne Konten: kundensicher 404. */
    private function assertBelongsToCustomer(Customer $customer, User $portalUser): void {
        abort_unless(
            $portalUser->isCustomer()
            && (int) $portalUser->customer_id === (int) $customer->id
            && (int) $portalUser->organization_id === (int) $customer->organization_id,
            404,
        );
    }
}
