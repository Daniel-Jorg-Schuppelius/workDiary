<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordResetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-contained „Passwort vergessen"-Flow: Da der Login über einen Legacy-
 * Provider (Username + Pflicht-Passwort) läuft, ist Laravels Password-Broker
 * nicht nutzbar. Daher eigene Token-Verwaltung in password_reset_tokens.
 * Nutzer werden per E-Mail identifiziert. Antworten sind bewusst generisch,
 * um nicht zu verraten, ob eine E-Mail existiert.
 */
class PasswordResetController extends Controller {
    private function expireMinutes(): int {
        return (int) config('auth.passwords.users.expire', 60);
    }

    public function request(): View {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNull('customer_id')->first();
        if ($user instanceof User && $user->email) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );
            $url = route('password.reset', ['token' => $token]) . '?email=' . urlencode($user->email);
            $user->notify(new PasswordResetLink($url, $this->expireMinutes()));
        }

        // Immer generisch antworten (kein Account-Enumeration).
        return back()->with('status', __('Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen versendet.'));
    }

    public function reset(Request $request, string $token): View {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $email = mb_strtolower(trim($data['email']));

        $row = DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [$email])->first();
        $valid = $row !== null
            && Hash::check($data['token'], $row->token)
            && Carbon::parse($row->created_at)->addMinutes($this->expireMinutes())->isFuture();

        if (! $valid) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Dieser Link ist ungültig oder abgelaufen.')]);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNull('customer_id')->first();
        if (! $user instanceof User) {
            return back()->withErrors(['email' => __('Dieser Link ist ungültig oder abgelaufen.')]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $row->email)->delete();

        return redirect()->route('login')->with('status', __('Passwort geändert. Bitte melden Sie sich an.'));
    }
}
