<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\License;

use App\Models\Organization;
use App\Services\Licensing\LicenseService;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Console\Command;

class InstallCommand extends Command {
    protected $signature = 'license:install
        {key? : Lizenzschlüssel oder Pfad zu einer Datei mit dem Schlüssel}
        {--org= : Organisation (license_uid oder ID) – installiert org-gebunden statt global}
        {--stdin : Lizenzschlüssel von STDIN lesen}';

    protected $description = 'Installiert einen Lizenzschlüssel (global oder org-gebunden mit --org).';

    public function handle(LicenseService $service): int {
        $key = $this->resolveKey();
        if ($key === null || $key === '') {
            $this->error('Kein Lizenzschlüssel übergeben.');

            return self::FAILURE;
        }

        $orgOption = $this->option('org');
        if (is_string($orgOption) && $orgOption !== '') {
            $org = $this->resolveOrganization($orgOption);
            if ($org === null) {
                $this->error('Organisation nicht gefunden: ' . $orgOption);

                return self::FAILURE;
            }
            $result = $service->installForOrganization($org, $key);
            $plan = $result->payload !== null ? $result->payload->plan : '—';
            $this->line('Organisation: ' . $org->name . ' (#' . $org->getKey() . ')');
            $this->line('Status: ' . $result->status->value . ' · Plan: ' . $plan);
        } else {
            $result = $service->install($key);
            $this->line('Status: ' . $result->status->value);
        }

        if ($result->message !== null) {
            $this->line($result->message);
        }

        return $result->isUsable() ? self::SUCCESS : self::FAILURE;
    }

    private function resolveOrganization(string $ref): ?Organization {
        $org = Organization::withoutGlobalScopes()->where('license_uid', $ref)->first();
        if ($org === null && ctype_digit($ref)) {
            $org = Organization::withoutGlobalScopes()->find((int) $ref);
        }

        return $org;
    }

    private function resolveKey(): ?string {
        if ($this->option('stdin')) {
            $stdin = trim((string) stream_get_contents(STDIN));

            return $stdin !== '' ? $stdin : null;
        }

        $arg = $this->argument('key');
        if (! is_string($arg) || $arg === '') {
            return null;
        }

        if (ToolkitFile::isReadable($arg)) {
            return trim(ToolkitFile::read($arg));
        }

        return trim($arg);
    }
}
