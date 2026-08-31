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
use App\Services\Auth\UserSessionInvalidator;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-contained „Passwort vergessen"-Flow: Da der Login über den Legacy-
 * Provider läuft, ist Laravels Password-Broker nicht nutzbar → eigene
 * Token-Verwaltung in password_reset_tokens. Antworten bewusst generisch
 * (keine Account-Enumeration).
 */
class PasswordResetController extends Controller {
    private function expireMinutes(): int {
        return (int) config('auth.passwords.users.expire', 60);
    }

    public function request(): View {
        return view('auth.forgot-password');
    }

    /** Mindestabstand zwischen zwei Reset-Mails an dieselbe Adresse. */
    private const RESEND_COOLDOWN_MINUTES = 2;

    public function email(Request $request): RedirectResponse {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNull('customer_id')->first();
        if ($user instanceof User && $user->email && ! $this->sentRecently($user->email)) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );
            $url = $this->resetUrl($token, $user->email);
            $user->notify(new PasswordResetLink($url, $this->expireMinutes()));
        }

        // Immer generisch antworten (kein Account-Enumeration) — auch dann,
        // wenn wegen der Sperrfrist gar keine Mail rausging: sonst verriete
        // schon die Antwort, dass es das Konto gibt.
        return back()->with('status', __('Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen versendet.'));
    }

    /**
     * Mindestabstand zwischen zwei Reset-Mails an dieselbe Adresse
     * (Sicherheitsscan 2026-08-23, S-45).
     *
     * Der Limiter an der Route deckelt die Zahl der Anfragen; diese Sperre
     * deckelt die Zahl der **Mails** und wirkt damit auch, wenn Anfragen aus
     * verteilten Quellen kommen. Sie ersetzt den Limiter nicht, sie ergänzt
     * ihn an der Stelle, an der die Belästigung entsteht.
     */
    private function sentRecently(string $email): bool {
        $last = DB::table('password_reset_tokens')->where('email', $email)->value('created_at');

        if ($last === null) {
            return false;
        }

        return CarbonImmutable::parse($last)->greaterThan(CarbonImmutable::now()->subMinutes(self::RESEND_COOLDOWN_MINUTES));
    }

    public function reset(Request $request, string $token): View {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request, UserSessionInvalidator $sessions): RedirectResponse {
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

        // is_new_system aktivieren, sonst prüft der LegacyUserProvider weiter das alte Klartext-Legacy-Passwort.
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'is_new_system' => true,
            'must_change_password' => false,
        ])->save();

        // Reset entwertet ALLE bestehenden Sitzungen (Account-Takeover-Schutz).
        $sessions->invalidateAll($user);

        DB::table('password_reset_tokens')->where('email', $row->email)->delete();

        return redirect()->route('login')->with('status', __('Passwort geändert. Bitte melden Sie sich an.'));
    }

    /**
     * Reset-Link aus der **konfigurierten** Adresse bauen, nicht aus dem
     * Host-Header.
     *
     * `route()` bildet die Wurzel aus `Request::root()` — und die kommt vom
     * Host-Header bzw. bei `TRUSTED_PROXIES=*` sogar aus `X-Forwarded-Host`.
     * Der Aufruf ist unauthentifiziert: ein Angreifer mit der E-Mail-Adresse
     * des Opfers konnte damit eine **echte** Reset-Mail auslösen, deren Link
     * auf seinen eigenen Server zeigt (Sicherheitsscan 2026-08-23, S-11).
     * Klickt das Opfer, hat er Token und Adresse.
     *
     * Dieselbe Härtung, die {@see \App\Services\Auth\WebAuthnService} schon
     * hat: `app.url` gewinnt, solange sie gesetzt und nicht die lokale
     * Entwicklungsadresse ist.
     */
    private function resetUrl(string $token, string $email): string {
        $path = route('password.reset', ['token' => $token], absolute: false);
        $configured = rtrim((string) config('app.url', ''), '/');
        $host = parse_url($configured, PHP_URL_HOST);

        $base = is_string($host) && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true)
            ? $configured
            : rtrim(url('/'), '/');

        return $base . $path . '?email=' . urlencode($email);
    }

}
