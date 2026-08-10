<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvitationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Services\CustomerPortal\PortalAccessService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Einladungs-Annahme fürs Kundenportal (MVP-510): einmaliger, befristeter
 * Link zur Passwortvergabe. Ungültige, abgelaufene oder widerrufene Tokens
 * antworten neutral mit 404 — ohne zu verraten, ob oder wo ein Konto existiert.
 */
class InvitationController extends Controller {
    public function __construct(private readonly PortalAccessService $service) {}

    public function show(string $token): View {
        $portalUser = $this->service->resolveInvite($token);
        abort_if($portalUser === null, 404);

        return view('customer.invitation', [
            'portalUser' => $portalUser,
            'token' => $token,
        ]);
    }

    public function store(string $token, Request $request): RedirectResponse {
        $portalUser = $this->service->resolveInvite($token);
        abort_if($portalUser === null, 404);

        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $this->service->accept($portalUser, (string) $data['password']);

        return redirect()->route('customer.login')
            ->with('status', __('Ihr Passwort wurde gesetzt. Sie können sich jetzt anmelden.'));
    }
}
