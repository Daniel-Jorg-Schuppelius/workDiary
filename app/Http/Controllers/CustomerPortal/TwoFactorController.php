<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Auth\TwoFactorType;
use App\Http\Controllers\Controller;
use App\Models\Auth\TwoFactorCredential;
use App\Models\User;
use App\Services\Auth\{EmailOtpService, TwoFactorService, WebAuthnService};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/** 2FA-Selbstverwaltung im Customer-Portal (guard 'customer'), Mehr-Methoden. */
class TwoFactorController extends Controller {
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly EmailOtpService $emailOtp,
        private readonly WebAuthnService $webauthn,
    ) {}

    public function webauthnOptions(Request $request): JsonResponse {
        $user = $this->user();
        $json = $this->webauthn->optionsToJson($this->webauthn->creationOptions($user, $request->getSchemeAndHttpHost()));
        $request->session()->put('webauthn.register', $json);

        return response()->json(json_decode($json, true));
    }

    public function webauthnRegister(Request $request): JsonResponse {
        $user = $this->user();
        $optionsJson = $request->session()->pull('webauthn.register');
        if (! is_string($optionsJson)) {
            return response()->json(['message' => __('Sitzung abgelaufen. Bitte erneut versuchen.')], 422);
        }
        try {
            $options = $this->webauthn->creationOptionsFromJson($optionsJson);
            $this->webauthn->verifyRegistration($user, $request->getContent(), $options, $request->getSchemeAndHttpHost(), $request->input('label'));
        } catch (\Throwable) {
            return response()->json(['message' => __('Sicherheitsschlüssel konnte nicht registriert werden.')], 422);
        }
        if (empty($user->two_factor_recovery_codes)) {
            $request->session()->flash('two_factor_recovery_codes', $this->twoFactor->ensureRecoveryCodes($user));
        }
        $request->session()->flash('success', __('Sicherheitsschlüssel / Passkey aktiviert.'));

        return response()->json(['ok' => true]);
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        return $user;
    }

    public function show(Request $request): View {
        $user = $this->user();
        $pendingTotp = filled($user->two_factor_secret) && $user->two_factor_confirmed_at === null;
        $pendingEmail = $user->twoFactorCredentials()
            ->where('type', TwoFactorType::Email->value)->whereNull('confirmed_at')->first();

        return view('customer.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'credentials' => $user->twoFactorCredentials()
                ->whereNotNull('confirmed_at')->where('type', '!=', TwoFactorType::Totp->value)->get(),
            'hasTotp' => $user->two_factor_confirmed_at !== null && filled($user->two_factor_secret),
            'pendingTotp' => $pendingTotp,
            'pendingEmail' => $pendingEmail,
            'qrSvg' => $pendingTotp ? $this->twoFactor->qrSvg($user, (string) $user->two_factor_secret) : null,
            'secret' => $pendingTotp ? (string) $user->two_factor_secret : null,
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes'),
        ]);
    }

    public function enable(): RedirectResponse {
        $this->user()->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('customer.2fa.show');
    }

    public function confirm(Request $request): RedirectResponse {
        $user = $this->user();
        $data = $request->validate(['code' => ['required', 'string']]);

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->route('customer.2fa.show');
        }
        if (blank($user->two_factor_secret) || ! $this->twoFactor->verify((string) $user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return redirect()->route('customer.2fa.show')
            ->with('two_factor_recovery_codes', $this->twoFactor->ensureRecoveryCodes($user))
            ->with('success', __('Authenticator-App aktiviert.'));
    }

    public function enableEmail(): RedirectResponse {
        $user = $this->user();
        $user->twoFactorCredentials()->firstOrCreate(
            ['type' => TwoFactorType::Email->value],
            ['label' => $user->email, 'confirmed_at' => null],
        );
        if (! $this->emailOtp->canSend($user)) {
            return back()->withErrors(['email_code' => __('Zu viele Anfragen. Bitte später erneut versuchen.')]);
        }
        if (! $this->emailOtp->send($user)) {
            return back()->withErrors(['email_code' => __('Code konnte nicht gesendet werden.')]);
        }

        return redirect()->route('customer.2fa.show')->with('success', __('Code an Ihre E-Mail gesendet.'));
    }

    public function resendEmailCode(): RedirectResponse {
        $user = $this->user();
        if (! $this->emailOtp->canSend($user)) {
            return back()->withErrors(['email_code' => __('Zu viele Anfragen. Bitte später erneut versuchen.')]);
        }
        if (! $this->emailOtp->send($user)) {
            return back()->withErrors(['email_code' => __('Code konnte nicht gesendet werden.')]);
        }

        return back()->with('success', __('Neuer Code gesendet.'));
    }

    public function confirmEmail(Request $request): RedirectResponse {
        $user = $this->user();
        $data = $request->validate(['email_code' => ['required', 'string']]);

        $credential = $user->twoFactorCredentials()->where('type', TwoFactorType::Email->value)->first();
        if ($credential === null) {
            return redirect()->route('customer.2fa.show');
        }
        if (! $this->emailOtp->verify($user, $data['email_code'])) {
            return back()->withErrors(['email_code' => __('Der Code ist ungültig oder abgelaufen.')]);
        }

        $credential->forceFill(['confirmed_at' => now(), 'last_used_at' => now()])->save();

        return redirect()->route('customer.2fa.show')
            ->with('two_factor_recovery_codes', $this->twoFactor->ensureRecoveryCodes($user))
            ->with('success', __('E-Mail-Code als zweiter Faktor aktiviert.'));
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse {
        $user = $this->user();
        $data = $request->validate(['code' => ['required', 'string']]);

        if (! $user->hasTwoFactorEnabled() || ! $this->twoFactor->verify((string) $user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        return redirect()->route('customer.2fa.show')->with('two_factor_recovery_codes', $codes);
    }

    public function removeCredential(TwoFactorCredential $credential): RedirectResponse {
        $user = $this->user();
        abort_unless((int) $credential->user_id === (int) $user->getKey(), 404);

        $remaining = $user->twoFactorCredentials()->whereNotNull('confirmed_at')->where('id', '!=', $credential->id)->count()
            + ($user->two_factor_confirmed_at !== null && filled($user->two_factor_secret) ? 1 : 0);
        if ($remaining === 0 && $user->organization?->two_factor_required) {
            return back()->withErrors(['credential' => __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; der letzte Faktor kann nicht entfernt werden.')]);
        }

        $credential->delete();

        return redirect()->route('customer.2fa.show')->with('success', __('Faktor entfernt.'));
    }

    public function disable(Request $request): RedirectResponse {
        $user = $this->user();
        $data = $request->validate(['code' => ['required', 'string']]);

        $secretOk = filled($user->two_factor_secret) && $this->twoFactor->verify((string) $user->two_factor_secret, $data['code']);
        $recoveryOk = $this->twoFactor->matchesRecoveryCode($user, $data['code']);
        if (! $secretOk && ! $recoveryOk) {
            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }
        if ($user->organization?->two_factor_required) {
            return back()->withErrors(['code' => __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.')]);
        }

        $user->twoFactorCredentials()->delete();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('customer.2fa.show')->with('success', __('Zwei-Faktor-Authentifizierung deaktiviert.'));
    }
}
