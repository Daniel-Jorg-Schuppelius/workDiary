<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SealCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\License;

use App\Services\Licensing\LicenseSeal;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\{File as ToolkitFile, Folder as ToolkitFolder};
use Illuminate\Console\Command;

class SealCommand extends Command {
    protected $signature = 'license:seal
        {--public-key= : Ed25519 Public Key (base64url). Fallback: LICENSE_PUBLIC_KEY}
        {--unseal : Entfernt die Seal-Datendatei (App fällt auf env-Konfig zurück).}';

    protected $description = 'Versiegelt Public Key und Datei-Hashes der lizenzrelevanten Dateien in der Seal-Datendatei.';

    /**
     * Relativ zum Projekt-Root. LicenseSeal.php ist hier enthalten — der
     * Klassen-Code ist stabil (keine generierten Konstanten mehr) und kann
     * deshalb mit-versiegelt werden.
     *
     * @var list<string>
     */
    private const SEALED_FILES = [
        'app/Services/Licensing/LicenseService.php',
        'app/Services/Licensing/LicenseSeal.php',
        'app/Services/Licensing/LicensePayload.php',
        'app/Services/Licensing/LicenseResult.php',
        'app/Services/Licensing/LicenseStatus.php',
        'app/Http/Middleware/EnsureValidLicense.php',
        'app/Http/Controllers/LicenseController.php',
        'config/license.php',
    ];

    public function handle(): int {
        $sealPath = LicenseSeal::path();

        if ($this->option('unseal')) {
            if (ToolkitFile::exists($sealPath)) {
                try {
                    ToolkitFile::delete($sealPath);
                } catch (\Throwable) {
                    $this->error('Seal-Datei konnte nicht entfernt werden: ' . $sealPath);

                    return self::FAILURE;
                }
            }
            LicenseSeal::flushCache();
            $this->info('Seal entfernt: ' . $sealPath);

            return self::SUCCESS;
        }

        $publicKey = (string) ($this->option('public-key') ?? config('license.public_key', ''));
        if ($publicKey === '') {
            $this->error('Kein Public Key übergeben. Nutze --public-key=... oder setze LICENSE_PUBLIC_KEY.');

            return self::FAILURE;
        }

        $hashes = [];
        foreach (self::SEALED_FILES as $relative) {
            $absolute = base_path($relative);
            if (! ToolkitFile::exists($absolute)) {
                $this->error('Datei nicht gefunden: ' . $relative);

                return self::FAILURE;
            }
            $hashes[$relative] = ToolkitFile::hash($absolute);
        }

        $sealedAt = CarbonImmutable::now()->toIso8601String();
        $this->writeSeal($sealPath, $publicKey, $hashes, $sealedAt);
        LicenseSeal::flushCache();

        $this->info('Seal geschrieben: ' . $sealPath);
        $this->line('  Public Key: ' . substr($publicKey, 0, 16) . '…');
        $this->line('  Hashes: ' . count($hashes) . ' Datei(en)');
        $this->line('  Sealed at: ' . $sealedAt);

        $this->warnAboutFilePermissions($sealPath);

        return self::SUCCESS;
    }

    /**
     * Weist deutlich darauf hin, dass die Seal-Datei mit den Rechten des
     * ausführenden Nutzers (z. B. root bei `sudo`) angelegt wird. Ist sie für
     * den Webserver-Nutzer nicht lesbar, schlägt jeder Request mit „Permission
     * denied" fehl (LicenseSeal::data() fällt dann auf env zurück).
     */
    private function warnAboutFilePermissions(string $path): void {
        $owner = function_exists('fileowner') ? @fileowner($path) : false;
        $ownerName = is_int($owner) && function_exists('posix_getpwuid')
            ? (posix_getpwuid($owner)['name'] ?? (string) $owner)
            : ($owner === false ? 'unbekannt' : (string) $owner);

        $perms = @fileperms($path);
        $mode = is_int($perms) ? substr(sprintf('%o', $perms), -4) : '----';

        $this->newLine();
        $this->warn('Hinweis zu Dateirechten:');
        $this->line('  Eigentümer: ' . $ownerName . '   Modus: ' . $mode);
        $this->line('  Die Seal-Datei wurde mit den Rechten des ausführenden Nutzers angelegt.');
        $this->line('  Wird der Befehl als root (z. B. via sudo) ausgeführt, gehört die Datei root');
        $this->line('  und ist für den Webserver-Nutzer oft NICHT lesbar -> 500 "Permission denied".');
        $this->line('  Stelle sicher, dass der Webserver-Nutzer die Datei lesen kann, z. B.:');
        $this->line('    chown <webuser>:<webgroup> ' . $path);
        $this->line('    chmod 640 ' . $path);
    }

    /**
     * @param  array<string, string>  $hashes
     */
    private function writeSeal(string $path, string $publicKey, array $hashes, string $sealedAt): void {
        $directory = dirname($path);
        if (! ToolkitFolder::exists($directory)) {
            ToolkitFolder::create($directory, 0700, true);
        }

        $payload = [
            'public_key' => $publicKey,
            'files' => $hashes,
            'sealed_at' => $sealedAt,
        ];

        $content = "<?php\n\n"
            . "// Generiert durch `php artisan license:seal`. Nicht manuell bearbeiten.\n\n"
            . 'return ' . var_export($payload, true) . ";\n";

        ToolkitFile::write($path, $content);
        @chmod($path, 0600);
    }
}
