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

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/** 2FA-Selbstverwaltung im Customer-Portal (guard 'customer'). */
class TwoFactorController extends Controller {
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    private function user(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        return $user;
    }

    public function show(Request $request): View {
        $user = $this->user();
        $pending = filled($user->two_factor_secret) && $user->two_factor_confirmed_at === null;

        return view('customer.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pending,
            'qrSvg' => $pending ? $this->twoFactor->qrSvg($user, (string) $user->two_factor_secret) : null,
            'secret' => $pending ? (string) $user->two_factor_secret : null,
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes'),
        ]);
    }

    public function enable(): RedirectResponse {
        $this->user()->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => $this->twoFactor->newRecoveryCodes(),
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
            ->with('two_factor_recovery_codes', (array) $user->two_factor_recovery_codes)
            ->with('success', __('Zwei-Faktor-Authentifizierung ist aktiv.'));
    }

    public function disable(Request $request): RedirectResponse {
        $user = $this->user();
        $data = $request->validate(['code' => ['required', 'string']]);

        $secretOk = filled($user->two_factor_secret) && $this->twoFactor->verify((string) $user->two_factor_secret, $data['code']);
        $recoveryOk = in_array(trim($data['code']), (array) ($user->two_factor_recovery_codes ?? []), true);
        if (! $secretOk && ! $recoveryOk) {
            return back()->withErrors(['code' => __('Der Code ist ungültig.')]);
        }
        if ($user->organization?->two_factor_required) {
            return back()->withErrors(['code' => __('Ihre Organisation verlangt Zwei-Faktor-Authentifizierung; Deaktivieren ist nicht möglich.')]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('customer.2fa.show')->with('success', __('Zwei-Faktor-Authentifizierung deaktiviert.'));
    }
}
