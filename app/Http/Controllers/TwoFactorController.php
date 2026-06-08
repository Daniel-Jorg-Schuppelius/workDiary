<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * Selbstverwaltung der Zwei-Faktor-Authentifizierung: aktivieren (Secret +
 * QR), per Code bestätigen, Recovery-Codes, deaktivieren.
 */
class TwoFactorController extends Controller {
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function show(Request $request): View {
        /** @var User $user */
        $user = Auth::user();

        $pending = filled($user->two_factor_secret) && $user->two_factor_confirmed_at === null;
        $qr = $pending ? $this->twoFactor->qrSvg($user, (string) $user->two_factor_secret) : null;

        return view('account.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pending,
            'qrSvg' => $qr,
            'secret' => $pending ? (string) $user->two_factor_secret : null,
            // Recovery-Codes nur unmittelbar nach Bestätigung/Neuerzeugung (Flash).
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes'),
        ]);
    }

    /** Aktivierung starten: Secret + Recovery-Codes erzeugen (noch unbestätigt). */
    public function enable(): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => $this->twoFactor->newRecoveryCodes(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('account.2fa.show');
    }

    /** Aktivierung bestätigen: TOTP-Code prüfen. */
    public function confirm(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate(['code' => ['required', 'string']]);

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->route('account.2fa.show');
        }
        if (blank($user->two_factor_secret) || ! $this->twoFactor->verify((string) $user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        // Recovery-Codes einmalig anzeigen.
        return redirect()->route('account.2fa.show')
            ->with('two_factor_recovery_codes', (array) $user->two_factor_recovery_codes)
            ->with('success', __('Zwei-Faktor-Authentifizierung ist aktiv.'));
    }

    /** Recovery-Codes neu erzeugen (erfordert gültigen aktuellen Code). */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate(['code' => ['required', 'string']]);

        if (! $user->hasTwoFactorEnabled() || ! $this->twoFactor->verify((string) $user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        $codes = $this->twoFactor->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('account.2fa.show')->with('two_factor_recovery_codes', $codes);
    }

    /** Deaktivieren: erfordert gültigen aktuellen TOTP- oder Recovery-Code. */
    public function disable(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate(['code' => ['required', 'string']]);

        $secretOk = filled($user->two_factor_secret) && $this->twoFactor->verify((string) $user->two_factor_secret, $data['code']);
        $recoveryOk = in_array(trim($data['code']), (array) ($user->two_factor_recovery_codes ?? []), true);
        if (! $secretOk && ! $recoveryOk) {
            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        // Org-Pflicht: Deaktivieren nur erlaubt, wenn die Org 2FA nicht erzwingt.
        if ($user->organization?->two_factor_required) {
            return back()->withErrors(['code' => __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.')]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('account.2fa.show')->with('success', __('Zwei-Faktor-Authentifizierung deaktiviert.'));
    }
}
