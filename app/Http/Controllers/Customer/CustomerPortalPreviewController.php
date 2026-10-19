<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalPreviewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Enums\User\Permission;
use App\Http\Controllers\Admin\SupportImpersonationController;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\CustomerPortal\{PortalAccessService, PortalPreview};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Symfony\Component\HttpFoundation\Response;

/**
 * Vorschau als Kunde (Phase 153, MVP-1229): Start aus der Kundenakte, Ende
 * aus der Vorschau-Leiste oder der Kundenakte. Die Vorschau ist eine Sicht
 * auf ein konkretes Portalkonto, keine Anmeldung ({@see PortalPreview}).
 */
class CustomerPortalPreviewController extends Controller {
    public function __construct(
        private readonly PortalPreview $preview,
        private readonly PortalAccessService $access,
    ) {}

    public function start(Request $request, Customer $customer, User $portalUser): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->isAdmin() || Gate::allows(Permission::CustomerPortalPreview->value), 403);
        Gate::authorize('view', $customer);

        // Fremde Organisationen, fremde Kunden und interne Konten: kundensicher 404.
        abort_unless(
            $portalUser->isCustomer()
            && (int) $portalUser->customer_id === (int) $customer->getKey()
            && (int) $portalUser->organization_id === (int) $customer->organization_id,
            404,
        );
        abort_if(
            $this->access->state($portalUser) === PortalAccessService::STATE_DEACTIVATED,
            Response::HTTP_FORBIDDEN,
            __('customer_portal.preview.deactivated'),
        );
        // Keine Verschachtelung: in einer Support-Sitzung handelt die Mitarbeiterin bereits als jemand anderes.
        abort_if(
            $request->session()->has(SupportImpersonationController::SESSION_KEY),
            Response::HTTP_FORBIDDEN,
            __('customer_portal.preview.nested_support'),
        );

        $this->preview->start($request, $customer, $portalUser, $actor);

        return redirect()->route('customer.dashboard');
    }

    public function stop(Request $request): Response {
        if ($this->preview->state($request) === null) {
            return Auth::guard('web')->check()
                ? redirect()->toList('customers.index')
                : redirect()->route('customer.login');
        }

        return $this->preview->endAndRedirect($request, PortalPreview::REASON_MANUAL);
    }
}
