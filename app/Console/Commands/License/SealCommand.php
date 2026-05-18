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

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SealCommand extends Command
{
    protected $signature = 'license:seal
        {--public-key= : Ed25519 Public Key (base64url). Fallback: env(LICENSE_PUBLIC_KEY)}
        {--unseal : Setzt die Seal-Datei zurück (Public Key + Hashes leeren).}';

    protected $description = 'Versiegelt Public Key und Datei-Hashes der lizenzrelevanten Dateien in LicenseSeal.';

    /**
     * Relativ zum Projekt-Root. Die LicenseSeal.php selbst darf hier NICHT
     * stehen — sonst ändert sich der Hash mit jedem Sealing.
     */
    private const SEALED_FILES = [
        'app/Services/Licensing/LicenseService.php',
        'app/Services/Licensing/LicensePayload.php',
        'app/Services/Licensing/LicenseResult.php',
        'app/Services/Licensing/LicenseStatus.php',
        'app/Http/Middleware/EnsureValidLicense.php',
        'app/Http/Controllers/LicenseController.php',
        'config/license.php',
    ];

    private const SEAL_PATH = 'app/Services/Licensing/LicenseSeal.php';

    public function handle(): int
    {
        if ($this->option('unseal')) {
            $this->writeSeal('', [], '');
            $this->info('Seal zurückgesetzt: '.self::SEAL_PATH);

            return self::SUCCESS;
        }

        $publicKey = (string) ($this->option('public-key') ?? env('LICENSE_PUBLIC_KEY', ''));
        if ($publicKey === '') {
            $this->error('Kein Public Key übergeben. Nutze --public-key=... oder setze LICENSE_PUBLIC_KEY.');

            return self::FAILURE;
        }

        $hashes = [];
        foreach (self::SEALED_FILES as $relative) {
            $absolute = base_path($relative);
            if (! is_file($absolute)) {
                $this->error('Datei nicht gefunden: '.$relative);

                return self::FAILURE;
            }
            $hash = hash_file('sha256', $absolute);
            if (! is_string($hash)) {
                $this->error('Hash-Berechnung fehlgeschlagen für: '.$relative);

                return self::FAILURE;
            }
            $hashes[$relative] = $hash;
        }

        $sealedAt = CarbonImmutable::now()->toIso8601String();
        $this->writeSeal($publicKey, $hashes, $sealedAt);

        $this->info('Seal geschrieben: '.self::SEAL_PATH);
        $this->line('  Public Key: '.substr($publicKey, 0, 16).'…');
        $this->line('  Hashes: '.count($hashes).' Datei(en)');
        $this->line('  Sealed at: '.$sealedAt);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $hashes
     */
    private function writeSeal(string $publicKey, array $hashes, string $sealedAt): void
    {
        $filesBlock = $this->renderFilesBlock($hashes);
        $publicKeyEscaped = addslashes($publicKey);
        $sealedAtEscaped = addslashes($sealedAt);

        $lines = [
            '<?php',
            '',
            '/*',
            ' * Created on   : Mon May 18 2026',
            ' * Author       : Daniel Jörg Schuppelius',
            ' * Author Uri   : https://schuppelius.org',
            ' * Filename     : LicenseSeal.php',
            ' * License      : AGPL-3.0-or-later',
            ' * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html',
            ' *',
            ' * Generiert durch `php artisan license:seal`. Nicht manuell bearbeiten.',
            ' */',
            '',
            'namespace App\\Services\\Licensing;',
            '',
            'final class LicenseSeal',
            '{',
            "    public const PUBLIC_KEY = '".$publicKeyEscaped."';",
            '',
            '    /**',
            '     * @var array<string, string> relativer Pfad => sha256-hex',
            '     */',
            $filesBlock,
            '',
            "    public const SEALED_AT = '".$sealedAtEscaped."';",
            '',
            '    public static function isSealed(): bool',
            '    {',
            "        return self::PUBLIC_KEY !== '';",
            '    }',
            '}',
            '',
        ];

        file_put_contents(base_path(self::SEAL_PATH), implode("\n", $lines));
    }

    /**
     * @param  array<string, string>  $hashes
     */
    private function renderFilesBlock(array $hashes): string
    {
        if ($hashes === []) {
            return '    public const FILES = [];';
        }

        $lines = ['    public const FILES = ['];
        foreach ($hashes as $path => $hash) {
            $lines[] = "        '".addslashes($path)."' => '".$hash."',";
        }
        $lines[] = '    ];';

        return implode("\n", $lines);
    }
}
