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

use App\Services\Licensing\LicenseService;
use Illuminate\Console\Command;

class ShowCommand extends Command {
    protected $signature = 'license:show {--host= : Domain für die Prüfung simulieren}';

    protected $description = 'Zeigt Status und Inhalt der aktuell installierten Lizenz.';

    public function handle(LicenseService $service): int {
        $service->flush();
        $result = $service->current($this->option('host') ?: null);

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
