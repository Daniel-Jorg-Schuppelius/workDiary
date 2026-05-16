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

/**
 * Authentifiziert zuerst gegen die Legacy-Tabelle user (userpw als Klartext),
 * legt dann bei Erstlogin einen lokalen Datensatz in users an und
 * nutzt danach Standard-Eloquent für alle weiteren Checks.
 */
class LegacyUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher)
    {
        parent::__construct($hasher, User::class);
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $username = $credentials['username'] ?? $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;

        if (! $username || ! $password) {
            return null;
        }

        // Schritt 1: Legacy-Tabelle prüfen (userpw ist Klartext, varchar 15)
        $legacyUser = $this->findLegacyUser($username, $password);

        if ($legacyUser) {
            // Schritt 2: Lokalen User anlegen oder aktualisieren
            /** @var object{id: int, uname: string, email: string|null} $legacyUser */
            return User::updateOrCreate(
                ['legacy_user_id' => $legacyUser->id],
                [
                    'name' => $legacyUser->uname,
                    'email' => $legacyUser->email ?: $legacyUser->uname.'@workdiary.local',
                    'password' => bcrypt($password),
                ]
            );
        }

        // Schritt 3: Fallback auf lokale Users-Tabelle (für migrierte Accounts)
        return parent::retrieveByCredentials(['email' => $username, 'password' => $password]);
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        /** @var User $user */
        $password = $credentials['password'] ?? null;

        if (! $password) {
            return false;
        }

        // Wenn der User eine legacy_user_id hat, akzeptieren wir sowohl
        // das aktuelle bcrypt-Hash als auch ggf. ein re-synchronisiertes PW
        return $this->hasher->check($password, $user->getAuthPassword());
    }

    private function findLegacyUser(string $username, string $password): ?object
    {
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
