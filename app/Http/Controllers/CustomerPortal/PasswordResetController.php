<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordResetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Concerns\ChecksTenantPublicSurfaces;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\CustomerPortal\PortalAccessService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * „Passwort vergessen“ im Kundenportal (MVP-1096). Die Anfrage antwortet immer
 * gleich; ungültige, abgelaufene oder verbrauchte Links antworten neutral mit
 * 404 wie die Einladungsannahme. Anschließend gilt die normale Anmeldung
 * einschließlich zweitem Faktor.
 */
class PasswordResetController extends Controller {
    use ChecksTenantPublicSurfaces;

    public function __construct(private readonly PortalAccessService $service) {}

    public function request(): View {
        return view('customer.forgot-password');
    }

    public function email(Request $request): RedirectResponse {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $this->service->sendPasswordReset((string) $data['email']);

        return back()->with('status', __('Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen versendet.'));
    }

    public function show(Request $request, User $user): View {
        $this->assertValidLink($request, $user);

        return view('customer.invitation', [
            'portalUser' => $user,
            'action' => $request->getRequestUri(),
            'hint' => __('customer_portal.password.sessions_hint'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse {
        $this->assertValidLink($request, $user);

        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $this->service->resetPassword($user, (string) $data['password']);

        return redirect()->route('customer.login')
            ->with('status', __('Passwort geändert. Bitte melden Sie sich an.'));
    }

    private function assertValidLink(Request $request, User $user): void {
        abort_unless(
            URL::hasValidRelativeSignature($request)
            && $this->service->resolvePasswordReset($user, (string) $request->query('hash', '')),
            404,
        );
        $this->assertTenantPublicSurfacesAvailable((int) $user->organization_id);
    }
}
