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

        return self::SUCCESS;
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
