<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckCertificateExpiryCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Event;

use App\Services\Event\CertificateService;
use Illuminate\Console\Command;

class CheckCertificateExpiryCommand extends Command {
    protected $signature = 'events:check-certificates';

    protected $description = 'Benachrichtigt Teilnehmer über bald ablaufende Pflicht-Zertifikate.';

    public function handle(CertificateService $certificates): int {
        $notified = $certificates->notifyExpiring();
        $this->info("Notified {$notified} participant(s).");

        return self::SUCCESS;
    }
}
