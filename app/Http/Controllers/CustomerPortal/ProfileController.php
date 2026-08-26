<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerPortal\PortalEmailChangeService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Profil des Portalnutzers (Feature 012-Ausbau, MVP-712): Anzeige des
 * Kontos und Selbständerung der Anmelde-E-Mail — zweiphasig über einen
 * signierten Bestätigungslink an die NEUE Adresse ({@see PortalEmailChangeService}).
 * Die Portal-Antwort bleibt neutral (keine Konten-Enumeration).
 */
class ProfileController extends Controller {
    public function __construct(private readonly PortalEmailChangeService $emailChange) {}

    public function show(): View {
        $user = $this->portalUser();

        return view('customer.profile', [
            'user' => $user,
            'pendingEmail' => $user->portal_pending_email,
            'pendingRequestedAt' => $user->portal_pending_email_requested_at,
            'ttlHours' => PortalEmailChangeService::TTL_HOURS,
        ]);
    }

    public function requestEmailChange(Request $request): RedirectResponse {
        $user = $this->portalUser();
        abort_if($user->isDeactivated(), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ]);

        $this->emailChange->request($user, (string) $data['email']);

        // Neutral in beiden Fällen (frei/belegt): keine Rückschlüsse auf
        // fremde Konten.
        return redirect()->route('customer.profile.show')
            ->with('status', __('Sofern die Adresse verwendbar ist, haben wir einen Bestätigungslink an die neue E-Mail-Adresse gesendet. Die Änderung wird erst nach dem Klick wirksam.'));
    }

    /**
     * Bestätigung über den signierten Link (ohne Session, wie die
     * Einladungsannahme) — ungültig/abgelaufen/fremd antwortet neutral 404.
     */
    public function confirmEmailChange(Request $request, User $user): RedirectResponse {
        abort_unless($user->isCustomer(), 404);

        $old = $this->emailChange->confirm($user, (string) $request->query('hash', ''));
        abort_if($old === null, 404);

        $target = Auth::guard('customer')->check() ? route('customer.profile.show') : route('customer.login');

        return redirect($target)
            ->with('status', __('Ihre E-Mail-Adresse wurde geändert. Bitte verwenden Sie ab jetzt die neue Adresse zur Anmeldung.'));
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 404);

        return $user;
    }
}
