<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisStatusPageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Services\Crisis\CrisisStatusPageService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Verwaltung der öffentlichen Statusseite (MVP-915). Recht `organization.update`
 * wie beim Geräte-Pass: die Aktion öffnet eine Seite ohne Anmeldung.
 */
class CrisisStatusPageController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly CrisisStatusPageService $statusPage) {}

    public function edit(Request $request): View {
        Gate::authorize(P::OrganizationUpdate->value);

        return view('crisis._status_page_dialog', [
            'status' => $this->statusPage->status($this->currentOrganization()),
            'token' => $request->session()->get('crisis_status_token'),
        ]);
    }

    public function rotate(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);

        $token = $this->statusPage->issue($this->currentOrganization());

        return redirect()->route('crisis.status-page.edit')
            ->with('crisis_status_token', $token)
            ->with('success', __('crisis.status_page.flash.issued'));
    }

    public function revoke(): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);

        $this->statusPage->revoke($this->currentOrganization());

        return redirect()->route('crisis.status-page.edit')
            ->with('success', __('crisis.status_page.flash.revoked'));
    }

    public function toggle(Request $request): RedirectResponse {
        Gate::authorize(P::OrganizationUpdate->value);

        $this->statusPage->setEnabled($this->currentOrganization(), $request->boolean('enabled'));

        return redirect()->route('crisis.status-page.edit')
            ->with('success', __('crisis.status_page.flash.saved'));
    }
}
