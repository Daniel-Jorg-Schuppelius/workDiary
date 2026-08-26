<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsOptInController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\{Organization, User};
use App\Services\Notification\Sms\{SmsOptInService, SmsProviderResolver};
use App\Support\PhoneSearchKey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use RuntimeException;

/**
 * Selbstverwaltung des SMS-Opt-ins (Feature 147, MVP-730).
 *
 * Bewusst ohne Permission und ohne Admin-Weg: die Einwilligung in
 * Alarm-SMS gehört der Person selbst (Art. 7 DSGVO) — sie kann sie nur
 * selbst erteilen und jederzeit selbst widerrufen. Es gibt keine Aktion,
 * mit der jemand anderes ein Opt-in setzt.
 */
class SmsOptInController extends Controller {
    public function __construct(
        private readonly SmsOptInService $optIn,
        private readonly SmsProviderResolver $providers,
    ) {}

    /** Selbstverwaltungsseite (aus dem Profil-Dialog verlinkt). */
    public function index(): View {
        $user = $this->user();
        $organization = $user->organization;

        return view('account.sms-opt-in', [
            'active' => $this->optIn->hasOptIn($user),
            'hasMobile' => PhoneSearchKey::of($user->mobile) !== null,
            'hasGateway' => $organization instanceof Organization && $this->providers->hasGateway($organization),
        ]);
    }

    /** Schickt einen Bestätigungscode an die eigene Mobilnummer. */
    public function start(): RedirectResponse {
        try {
            $this->optIn->startVerification($this->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('sms.code_sent'));
    }

    /** Bestätigt den Code und schaltet das Opt-in frei. */
    public function confirm(Request $request): RedirectResponse {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        if (! $this->optIn->confirm($this->user(), (string) $data['code'])) {
            return back()->with('error', __('sms.code_invalid'));
        }

        return back()->with('success', __('sms.opt_in_active'));
    }

    /** Widerruf — ohne Bedingung und ohne Rückfrage an Dritte. */
    public function destroy(): RedirectResponse {
        $this->optIn->revoke($this->user());

        return back()->with('success', __('sms.opt_in_revoked'));
    }

    private function user(): User {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
