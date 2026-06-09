<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShowCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\License;

use App\Models\Organization;
use App\Services\Licensing\LicenseService;
use Illuminate\Console\Command;

class ShowCommand extends Command {
    protected $signature = 'license:show
        {--host= : Domain für die Prüfung simulieren}
        {--org= : Organisation (license_uid oder ID) – zeigt deren org-gebundene Lizenz}';

    protected $description = 'Zeigt Status und Inhalt der installierten Lizenz (global oder org-gebunden mit --org).';

    public function handle(LicenseService $service): int {
        $service->flush();

        $orgOption = $this->option('org');
        if (is_string($orgOption) && $orgOption !== '') {
            $org = Organization::withoutGlobalScopes()->where('license_uid', $orgOption)->first()
                ?? (ctype_digit($orgOption) ? Organization::withoutGlobalScopes()->find((int) $orgOption) : null);
            if ($org === null) {
                $this->error('Organisation nicht gefunden: ' . $orgOption);

                return self::FAILURE;
            }
            $this->line('Organisation: ' . $org->name . ' (license_uid ' . (string) $org->license_uid . ')');
            $service->flushOrganization($org);
            $result = $service->forOrganization($org);
        } else {
            $result = $service->current($this->option('host') ?: null);
        }

        $this->line('Status   : ' . $result->status->value);
        if ($result->message !== null) {
            $this->line('Hinweis  : ' . $result->message);
        }

        if ($result->payload !== null) {
            $p = $result->payload;
            $this->table(
                ['Feld', 'Wert'],
                [
                    ['license_id', $p->licenseId],
                    ['licensee', $p->licensee],
                    ['email', (string) $p->email],
                    ['plan', $p->plan],
                    ['addons', implode(', ', $p->addons) ?: '–'],
                    ['organization', (string) $p->organization ?: '– (ungebunden)'],
                    ['issued_at', $p->issuedAt->toDateTimeString()],
                    ['expires_at', $p->expiresAt?->toDateTimeString() ?? '–'],
                    ['domain', (string) $p->domain],
                    ['max_users', $p->maxUsers !== null ? (string) $p->maxUsers : '–'],
                    ['features', implode(', ', $p->features) ?: '–'],
                ],
            );
        }

        return $result->isUsable() ? self::SUCCESS : self::FAILURE;
    }
}
