<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeSyncUploadsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Customer\Intake\CustomerIntakeUploadChannels;
use Illuminate\Console\Command;

/**
 * Upload-Kanäle der Kundeneingänge abholen (MVP-1078): übernimmt neue Dateien
 * aller offenen Links und widerruft Links abgeschlossener oder abgelaufener
 * Eingänge nach der letzten Übernahme.
 */
class CustomerIntakeSyncUploadsCommand extends Command {
    protected $signature = 'customer-intakes:sync-uploads {--organization= : Nur diese Organisation (ID)}';

    protected $description = 'Kundeneingänge: Dateien aus Upload-Kanälen (Nextcloud) übernehmen';

    public function handle(CustomerIntakeUploadChannels $channels): int {
        $organization = $this->option('organization');
        $count = $channels->syncAll($organization !== null ? (int) $organization : null);
        $this->info("Upload-Links abgeholt: {$count}");

        return self::SUCCESS;
    }
}
