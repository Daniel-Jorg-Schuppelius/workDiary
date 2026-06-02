<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdminCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Install\InstallationManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\{password, text};

use Throwable;

/**
 * Legt einen Administrator an oder setzt dessen Passwort zurück – unabhängig
 * vom Web-Installer. Nützlich, wenn die Benutzertabelle leer ist (z. B. weil
 * ein früherer Installationslauf in die falsche Datenbank schrieb) oder ein
 * Admin sich ausgesperrt hat.
 */
class AdminCommand extends Command {
    protected $signature = 'app:admin
        {--email= : E-Mail des Administrators}
        {--name= : Anzeigename (nur beim Anlegen)}
        {--org= : Name der Organisation (nur beim Anlegen)}
        {--password= : Passwort (sonst interaktive Abfrage)}
        {--reset : Vorhandenen Benutzer aktualisieren statt neu anzulegen}';

    protected $description = 'Legt einen Administrator an oder setzt dessen Passwort zurück.';

    public function handle(InstallationManager $installer): int {
        $email = (string) ($this->option('email') ?: text('E-Mail des Administrators', required: true));

        $exists = User::where('email', $email)->exists();
        $reset = (bool) $this->option('reset') || $exists;

        try {
            if ($reset) {
                if (! $exists) {
                    $this->error("Kein Benutzer mit E-Mail {$email} gefunden.");

                    return self::FAILURE;
                }

                $pwd = $this->resolvePassword();
                $user = $installer->resetAdminPassword($email, $pwd);
                $this->info('✓ Passwort aktualisiert & Admin-Rolle sichergestellt: ' . $user->email);

                return self::SUCCESS;
            }

            $org = (string) ($this->option('org') ?: text('Name der Organisation', required: true));
            $name = (string) ($this->option('name') ?: text('Name des Administrators', required: true));
            $pwd = $this->resolvePassword();

            $user = $installer->createOrganizationAndAdmin([
                'org_name' => $org,
                'name' => $name,
                'email' => $email,
                'password' => $pwd,
            ]);
            $this->info('✓ Administrator angelegt: ' . $user->email);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Vorgang fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolvePassword(): string {
        $pwd = (string) $this->option('password');

        return $pwd !== '' ? $pwd : (string) password('Passwort', required: true);
    }
}
