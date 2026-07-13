<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationProvisioner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Install;

use App\Enums\User\UserRole;
use App\Models\{Organization, User};
use Illuminate\Support\Facades\{Config, DB, Hash};
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Installer-Baustein Organisation & Admin: Erst-Organisation samt
 * Admin-Benutzer anlegen bzw. Admin-Passwort zurücksetzen. Aus dem
 * InstallationManager extrahiert (Refactoring Welle 2, B6b); dieser bleibt
 * die Fassade.
 */
class OrganizationProvisioner {
    public function __construct(private readonly DatabaseConfigurator $database) {}

    /**
     * Legt die erste Organisation samt Admin-Benutzer an. Idempotent in dem
     * Sinne, dass eine bestehende Organisation mit gleichem Slug erweitert
     * statt dupliziert wird.
     *
     * @param  array{org_name: string, name: string, email: string, password: string}  $data
     * @param  bool  $platformAdmin  Erst-Betreiber (darf Org-Kontext wechseln).
     *         Installer setzen true; app:admin nur mit --platform.
     */
    public function createOrganizationAndAdmin(array $data, bool $platformAdmin = true): User {
        // Dieser Schritt läuft in einem eigenen HTTP-Request, in dem die
        // (ggf. gecachte) Config noch auf die alte Verbindung zeigen kann.
        // Daher die in der .env hinterlegte DB-Verbindung erneut aktivieren,
        // damit der Admin garantiert in der konfigurierten Datenbank landet.
        $this->database->applyConfiguredDatabaseToRuntime();

        // Spatie-Permission-Cache während der Anlage auf den array-Store legen.
        // Sonst schreibt die Cache-Invalidierung (assignRole) in die SQLite
        // `cache`-Tabelle, während diese Transaktion bereits einen Write-Lock
        // hält – das führt zu „database is locked“.
        $this->usePermissionArrayCache();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return DB::transaction(function () use ($data, $platformAdmin): User {
            $org = Organization::firstOrCreate(
                ['slug' => Str::slug($data['org_name']) ?: 'default'],
                [
                    'name' => $data['org_name'],
                    'plan' => Organization::PLAN_FREE,
                    'locale' => (string) config('app.locale', 'de'),
                    'timezone' => (string) config('app.timezone', 'Europe/Berlin'),
                    'is_active' => true,
                ],
            );

            /** @var User $user */
            $user = User::create([
                'organization_id' => $org->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_new_system' => true,
            ]);

            // Der über den Installer angelegte Erst-Admin ist der
            // Plattform-Betreiber (darf den Org-Kontext wechseln). Bewusst
            // separat gesetzt — is_platform_admin ist nicht massenzuweisbar.
            if ($platformAdmin) {
                $user->forceFill(['is_platform_admin' => true])->save();
            }

            if ($org->owner_id === null) {
                $org->update(['owner_id' => $user->id]);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
            $adminRole = Role::findOrCreate(UserRole::Admin->value, 'web');
            $user->assignRole($adminRole);

            return $user;
        });
    }

    /**
     * Setzt das Passwort eines bestehenden Benutzers neu und stellt sicher,
     * dass er die Admin-Rolle seiner Organisation besitzt. Reaktiviert vorher
     * die in der .env konfigurierte DB-Verbindung, damit auch bei gecachter
     * Config die richtige Datenbank getroffen wird.
     */
    public function resetAdminPassword(string $email, string $password): User {
        $this->database->applyConfiguredDatabaseToRuntime();

        $this->usePermissionArrayCache();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return DB::transaction(function () use ($email, $password): User {
            /** @var User|null $user */
            $user = User::where('email', $email)->first();
            if ($user === null) {
                throw new RuntimeException("Kein Benutzer mit E-Mail {$email} gefunden.");
            }

            // Cast 'password' => 'hashed' übernimmt das Hashing beim Speichern.
            // is_new_system aktivieren, damit der Login das neue bcrypt-Passwort
            // prüft und nicht weiter auf das Legacy-Klartextpasswort zurückfällt.
            $user->password = $password;
            $user->is_new_system = true;
            $user->save();

            if ($user->organization_id !== null) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($user->organization_id);
            }
            $adminRole = Role::findOrCreate(UserRole::Admin->value, 'web');
            $user->assignRole($adminRole);

            return $user;
        });
    }

    /**
     * Schaltet den Spatie-Permission-Cache auf den flüchtigen array-Store um.
     * Verhindert, dass Cache-Invalidierungen während einer offenen DB-Trans-
     * aktion in die (gleiche) SQLite-Datenbank schreiben und dort auf einen
     * Write-Lock laufen („database is locked“). Der frische Registrar wird neu
     * aus dem Container aufgelöst, damit die geänderte Store-Konfiguration greift.
     */
    private function usePermissionArrayCache(): void {
        Config::set('permission.cache.store', 'array');
        app()->forgetInstance(PermissionRegistrar::class);
    }
}
