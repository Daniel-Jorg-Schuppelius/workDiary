<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InteractsWithTwoFactor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use Illuminate\Support\Collection;

/**
 * Zwei-Faktor-Helfer des User-Modells (TOTP/Mail-OTP/WebAuthn über
 * two_factor_credentials plus Legacy-TOTP-Spalten). Aus dem User-Modell
 * extrahiert (Refactoring Welle 2, B6b) — die Relation
 * twoFactorCredentials() bleibt im Modell, Verhalten unverändert.
 */
trait InteractsWithTwoFactor {
    /**
     * Bestaetigte zweite Faktoren (alle Methoden).
     *
     * @return Collection<int, \App\Models\Auth\TwoFactorCredential>
     */
    public function confirmedTwoFactorCredentials(): Collection {
        return $this->twoFactorCredentials()->whereNotNull('confirmed_at')->get();
    }

    /** Zwei-Faktor aktiv: mindestens ein bestaetigter Faktor (oder Legacy-TOTP). */
    public function hasTwoFactorEnabled(): bool {
        return $this->twoFactorCredentials()->whereNotNull('confirmed_at')->exists()
            || ($this->two_factor_confirmed_at !== null && filled($this->two_factor_secret));
    }
}
