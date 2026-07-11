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

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Authentifiziert zuerst gegen die Legacy-Tabelle user (userpw als Klartext),
 * legt dann bei Erstlogin einen "Schatten"-Datensatz in users an (ohne
 * nutzbares Passwort und mit is_new_system=false) und nutzt danach Standard-
 * Eloquent f\u00fcr alle weiteren Checks. Reine Neu-Accounts (kein
 * legacy_user_id) werden \u00fcber den lokalen Hash gepr\u00fcft.
 *
 * Wichtig: Legacy-Passw\u00f6rter werden NIE in users.password \u00fcbernommen,
 * damit ein kompromittiertes Legacy-Passwort keinen Zugriff auf das neue
 * System verschaffen kann.
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

        // Schritt 1: Legacy-Tabelle pr\u00fcfen (userpw ist Klartext, varchar 15)
        $legacyUser = $this->findLegacyUser($username, $password);

        if ($legacyUser) {
            /** @var object{id: int, uname: string, email: string|null} $legacyUser */
            $existing = User::query()
                ->where('legacy_user_id', $legacyUser->id)
                ->whereNull('customer_id')
                ->first();

            if ($existing instanceof User) {
                // Vorhandenen Datensatz nur in unkritischen Feldern auffrischen.
                // password und is_new_system bleiben unangetastet.
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

        // Schritt 2: Fallback auf lokale Users-Tabelle (f\u00fcr im neuen System
        // freigeschaltete/portierte Accounts mit eigenem Passwort). Portal-
        // Accounts (`customer_id IS NOT NULL`) sind hier explizit ausgeschlossen,
        // damit sie nie ueber den internen Guard authentifiziert werden koennen.
        $candidate = parent::retrieveByCredentials(['email' => $username, 'password' => $password]);

        // Unbekannter oder als Portal-Account ausgeschlossener Login: Dummy-bcrypt-
        // Check, damit die Antwortzeit der eines bekannten Logins entspricht
        // (Schutz gegen User-Enumeration über Timing-Messung).
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
        // SSO-Pflicht (Feature 057, MVP-120): erzwingt eine Organisation SSO,
        // ist der Passwort-Login serverseitig gesperrt — Ausnahme ist nur das
        // Break-Glass-Konto (users.sso_exempt). Gilt für neu UND legacy.
        if (! $user->sso_exempt && \App\Models\SsoConnection::enforcementActiveFor($user->organization_id)) {
            return false;
        }
        // Portal-Accounts duerfen den internen Guard nicht passieren.
        if ($user->customer_id !== null) {
            return false;
        }
        // Neu-System-Accounts (inkl. ehemals Legacy-verkn\u00fcpfter, denen ein Admin
        // ein neues Passwort gesetzt hat) werden per bcrypt gegen users.password
        // gepr\u00fcft \u2013 unabh\u00e4ngig von einer Legacy-Verkn\u00fcpfung.
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
            return DB::connection('legacy')
                ->table('user')
                ->where('uname', $username)
                ->where('userpw', $password) // Klartext-Vergleich (Legacy-System)
                ->first();
        } catch (\Exception) {
            return null;
        }
    }
}
