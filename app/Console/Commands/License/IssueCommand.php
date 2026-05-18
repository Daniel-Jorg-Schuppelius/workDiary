<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssueCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\License;

use App\Services\Licensing\LicenseService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class IssueCommand extends Command
{
    protected $signature = 'license:issue
        {--licensee= : Name des Lizenznehmers (Pflicht)}
        {--email= : Kontakt-E-Mail}
        {--domain= : Domain-Bindung (z. B. app.kunde.de oder *.kunde.de)}
        {--expires= : Ablaufdatum (YYYY-MM-DD)}
        {--max-users= : Maximale Nutzerzahl}
        {--features=* : Feature-Flags}
        {--private-key= : Ed25519 Private Key (base64). Fallback: env(LICENSE_PRIVATE_KEY)}
        {--out= : Datei für den Lizenzschlüssel}';

    protected $description = 'Stellt einen signierten Lizenzschlüssel aus (nur beim Herausgeber auszuführen).';

    public function handle(): int
    {
        $licensee = (string) ($this->option('licensee') ?? '');
        if ($licensee === '') {
            $this->error('--licensee ist erforderlich.');

            return self::FAILURE;
        }

        $privateB64 = (string) ($this->option('private-key') ?? env('LICENSE_PRIVATE_KEY', ''));
        if ($privateB64 === '') {
            $this->error('Kein Private Key übergeben. Nutze --private-key=... oder setze LICENSE_PRIVATE_KEY.');

            return self::FAILURE;
        }

        $privateKey = LicenseService::b64Decode($privateB64);
        if ($privateKey === null || strlen($privateKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            $this->error('Private Key hat falsches Format.');

            return self::FAILURE;
        }

        $payload = [
            'license_id' => bin2hex(random_bytes(8)),
            'licensee' => $licensee,
            'email' => $this->option('email') ?: null,
            'issued_at' => CarbonImmutable::now()->toIso8601String(),
            'expires_at' => $this->option('expires')
                ? CarbonImmutable::parse((string) $this->option('expires'))->endOfDay()->toIso8601String()
                : null,
            'domain' => $this->option('domain') ?: null,
            'max_users' => $this->option('max-users') !== null ? (int) $this->option('max-users') : null,
            'features' => array_values(array_filter((array) $this->option('features'))),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('Payload-Serialisierung fehlgeschlagen.');

            return self::FAILURE;
        }

        $signature = sodium_crypto_sign_detached($json, $privateKey);
        $licenseKey = LicenseService::b64Encode($json).'.'.LicenseService::b64Encode($signature);

        $this->info('Lizenzschlüssel:');
        $this->line($licenseKey);

        $out = $this->option('out');
        if (is_string($out) && $out !== '') {
            file_put_contents($out, $licenseKey);
            @chmod($out, 0600);
            $this->info('Geschrieben nach: '.$out);
        }

        return self::SUCCESS;
    }
}
