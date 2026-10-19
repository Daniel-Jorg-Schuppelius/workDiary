<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\CustomerPortal\PortalAccessService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Passwortwechsel im Kundenportal (Phase 153, MVP-1233): freiwillig aus dem
 * Profil und als Pflicht nach einem Startpasswort (`must_change_password`,
 * {@see \App\Http\Middleware\ForcePasswordChange}). Das bisherige Passwort ist
 * die Bestätigung — auch beim Pflichtwechsel, der Kunde hat es gerade benutzt.
 */
class PasswordController extends Controller {
    public function __construct(private readonly PortalAccessService $service) {}

    public function edit(Request $request): View {
        /** @var User $user */
        $user = $request->user('customer');

        return view('customer.password-change', [
            'user' => $user,
            'forced' => (bool) $user->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse {
        /** @var User $user */
        $user = $request->user('customer');
        abort_if($user->isDeactivated(), 403);

        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:customer'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $this->service->changePassword($user, (string) $data['password'], $request->session()->getId());
        // Fixation-Schutz am Credential-Wechsel wie intern (AccountPasswordController).
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard')->with('success', __('customer_portal.password_change.flash'));
    }
}
