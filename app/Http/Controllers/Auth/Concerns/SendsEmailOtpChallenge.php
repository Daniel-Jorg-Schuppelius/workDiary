<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SendsEmailOtpChallenge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Concerns;

use App\Enums\Auth\TwoFactorType;
use App\Models\Platform\User;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * E-Mail-Einmalcode in der Zwei-Faktor-Abfrage (internes Login und
 * Kundenportal). Der Controller liefert die geparkte Identität, die
 * Login-Route und den Dienst `$emailOtp`.
 */
trait SendsEmailOtpChallenge {
    abstract private function parkedUser(Request $request): ?User;

    abstract private function loginRoute(): string;

    /** Sendet einen E-Mail-Einmalcode an die geparkte Identität. */
    public function email(Request $request): RedirectResponse {
        $user = $this->parkedUser($request);
        if (! $user instanceof User) {
            return redirect()->route($this->loginRoute());
        }
        if (! $this->hasEmailFactor($user) || ! $this->emailOtp->canSend($user)) {
            return back()->withErrors(['email_code' => __('Code konnte nicht gesendet werden.')]);
        }
        if (! $this->emailOtp->send($user)) {
            return back()->withErrors(['email_code' => __('E-Mail-Versand fehlgeschlagen — Mailserver nicht erreichbar oder falsch konfiguriert. Bitte informieren Sie Ihre Administration.')]);
        }

        return back()->with('success', __('Code an Ihre E-Mail gesendet.'));
    }

    private function hasEmailFactor(User $user): bool {
        return $user->twoFactorCredentials()
            ->where('type', TwoFactorType::Email->value)->whereNotNull('confirmed_at')->exists();
    }
}
