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

        // Schritt 1: Legacy-Tabelle pr\u00fcfen (userpw ist Klartext, varchar 15)
        $legacyUser = $this->findLegacyUser($username, $password);

        if ($legacyUser) {
            /** @var object{id: int, uname: string, email: string|null} $legacyUser */
            $existing = User::query()->where('legacy_user_id', $legacyUser->id)->first();

            if ($existing instanceof User) {
                // Vorhandenen Datensatz nur in unkritischen Feldern auffrischen.
                // password und is_new_system bleiben unangetastet.
                $existing->fill([
                    'name' => $legacyUser->uname,
                    'email' => $existing->email ?: ($legacyUser->email ?: $legacyUser->uname.'@workdiary.local'),
                ])->save();

                return $existing;
            }

            // Schatten-Datensatz anlegen: kein nutzbares Passwort, kein Neu-System-Zugriff.
            return User::create([
                'legacy_user_id' => $legacyUser->id,
                'name' => $legacyUser->uname,
                'email' => $legacyUser->email ?: $legacyUser->uname.'@workdiary.local',
                'password' => $this->hasher->make(Str::random(64)),
                'is_new_system' => false,
            ]);
        }

        // Schritt 2: Fallback auf lokale Users-Tabelle (f\u00fcr im neuen System
        // freigeschaltete/portierte Accounts mit eigenem Passwort).
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

        // Bei Legacy-Accounts: Klartext-Vergleich gegen Legacy-DB; users.password
        // ist nur ein zuf\u00e4lliger Platzhalter und darf NICHT akzeptiert werden.
        if ($user->legacy_user_id !== null) {
            return $this->findLegacyUser((string) $user->name, (string) $password) !== null;
        }

        // Reine Neu-Accounts: Standard-bcrypt-Pr\u00fcfung.
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
