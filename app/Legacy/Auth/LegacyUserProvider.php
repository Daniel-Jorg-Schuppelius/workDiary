<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyUserProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Auth;

use App\Legacy\Support\LegacyConnectivity;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Authentifiziert zuerst gegen die Legacy-Tabelle `user` (userpw als Klartext),
 * legt bei Erstlogin einen Schatten-Datensatz in `users` an (kein nutzbares
 * Passwort, is_new_system=false) und nutzt danach Standard-Eloquent.
 *
 * Wichtig: Legacy-Passw\u00f6rter werden NIE nach users.password \u00fcbernommen, damit
 * ein kompromittiertes Legacy-Passwort keinen Zugriff auf das neue System gibt.
 */
class LegacyUserProvider extends EloquentUserProvider {
    public function __construct(Hasher $hasher) {
        parent::__construct($hasher, User::class);
    }

    public function retrieveById($identifier): ?Authenticatable {
        $user = parent::retrieveById($identifier);

        if ($user instanceof User && $user->customer_id !== null) {
            return null;
        }

        return $user;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable {
        $user = parent::retrieveByToken($identifier, $token);

        if ($user instanceof User && $user->customer_id !== null) {
            return null;
        }

        return $user;
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable {
        $username = $credentials['username'] ?? $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;

        if (! $username || ! $password) {
            return null;
        }

        // Schritt 1: Legacy-Tabelle pr\u00fcfen (userpw ist Klartext).
        $legacyUser = $this->findLegacyUser($username, $password);

        if ($legacyUser) {
            /** @var object{id: int, uname: string, email: string|null} $legacyUser */
            $existing = User::query()
                ->where('legacy_user_id', $legacyUser->id)
                ->whereNull('customer_id')
                ->first();

            if ($existing instanceof User) {
                // Nur unkritische Felder auffrischen; password und is_new_system
                // bleiben unangetastet.
                $existing->fill([
                    'name' => $legacyUser->uname,
                    'email' => $existing->email ?: ($legacyUser->email ?: $legacyUser->uname . '@workdiary.local'),
                ])->save();

                return $existing;
            }

            // Schatten-Datensatz anlegen: kein nutzbares Passwort, kein Neu-System-Zugriff.
            return User::create([
                'legacy_user_id' => $legacyUser->id,
                'name' => $legacyUser->uname,
                'email' => $legacyUser->email ?: $legacyUser->uname . '@workdiary.local',
                'password' => $this->hasher->make(Str::random(64)),
                'is_new_system' => false,
            ]);
        }

        // Schritt 2: Fallback auf lokale users-Tabelle (portierte Accounts mit
        // eigenem Passwort). Portal-Accounts (customer_id IS NOT NULL) sind
        // ausgeschlossen \u2014 nie \u00fcber den internen Guard authentifizierbar.
        $candidate = parent::retrieveByCredentials(['email' => $username, 'password' => $password]);

        // Dummy-bcrypt-Check bei unbekanntem/ausgeschlossenem Login, damit die
        // Antwortzeit gleich bleibt (Schutz gegen Timing-User-Enumeration).
        if (! $candidate instanceof User || $candidate->customer_id !== null) {
            $this->equalizeTiming((string) $password);

            return null;
        }

        return $candidate;
    }

    /** Konstante Antwortzeit: ein bcrypt-Vergleich gegen einen festen Dummy-Hash. */
    private function equalizeTiming(string $password): void {
        static $dummyHash = null;
        $dummyHash ??= $this->hasher->make('timing-equalizer');
        $this->hasher->check($password, $dummyHash);
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool {
        /** @var User $user */
        $password = $credentials['password'] ?? null;

        if (! $password) {
            return false;
        }
        // Zentral deaktivierte Konten (Offboarding via Verzeichnisdienst,
        // Feature 057) sind überall gesperrt — kein Login-Pfad akzeptiert sie.
        if (! $user->canLogin()) {
            return false;
        }
        // SSO-Pflicht (Feature 057, MVP-120): erzwingt eine Org SSO, ist der
        // Passwort-Login gesperrt — Ausnahme nur Break-Glass (users.sso_exempt).
        if (! $user->sso_exempt && \App\Models\SsoConnection::enforcementActiveFor($user->organization_id)) {
            return false;
        }
        // Portal-Accounts duerfen den internen Guard nicht passieren.
        if ($user->customer_id !== null) {
            return false;
        }
        // Abgeschaltete Organisation: kein Login (Sicherheitsscan 2026-08-23,
        // S-04). Vorher konnte sich ein Mitarbeiter einer wegen Zahlungsverzug
        // deaktivierten Organisation weiter anmelden — und lief mangels
        // gebundener Organisation ungescopt durch alle Mandanten.
        if ($user->organization_id !== null && ! $user->isGlobalAdmin()) {
            $org = $user->organization;
            if (! $org instanceof \App\Models\Organization || ! $org->is_active) {
                return false;
            }
        }
        // Neu-System-Accounts (inkl. ehemals Legacy-verkn\u00fcpfter mit neuem
        // Passwort): bcrypt gegen users.password, unabh\u00e4ngig von Legacy.
        if ($user->is_new_system) {
            return $this->hasher->check($password, $user->getAuthPassword());
        }
        // Reine Legacy-Accounts: Klartext-Vergleich gegen Legacy-DB; users.password
        // ist nur ein zuf\u00e4lliger Platzhalter und darf NICHT akzeptiert werden.
        if ($user->legacy_user_id !== null) {
            return $this->findLegacyUser((string) $user->name, (string) $password) !== null;
        }

        // Reine Neu-Accounts: Standard-bcrypt-Pr\u00fcfung.
        return $this->hasher->check($password, $user->getAuthPassword());
    }

    private function findLegacyUser(string $username, string $password): ?object {
        if (! filled(config('database.connections.legacy.database'))) {
            return null;
        }

        try {
            // attempt() überspringt den Connect, wenn die legacy-DB als down
            // markiert ist — sonst kostet ein toter Host jeden Login einen Timeout.
            return LegacyConnectivity::attempt(
                fn (): ?object => DB::connection('legacy')
                    ->table('user')
                    ->where('uname', $username)
                    ->where('userpw', $password) // Klartext-Vergleich (Legacy-System)
                    ->first(),
                null,
            );
        } catch (\Exception) {
            return null;
        }
    }
}
