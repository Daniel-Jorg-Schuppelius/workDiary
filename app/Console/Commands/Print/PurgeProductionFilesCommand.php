<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurgeProductionFilesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Print;

use App\Services\Print\PrintOrderService;
use Illuminate\Console\Command;

/**
 * Löschfristen der Druck-Produktionsdateien durchsetzen (MVP-459):
 * entfernt abgelaufene Kundendateien tenant-sicher aus dem Storage —
 * Auftrag, Produktions-Snapshot und Datei-Hash bleiben als
 * aufbewahrungspflichtiger kaufmännischer Nachweis erhalten.
 */
class PurgeProductionFilesCommand extends Command {
    protected $signature = 'print:purge-files';

    protected $description = 'Entfernt Druck-Produktionsdateien nach Ablauf ihrer Löschfrist (Nachweise bleiben erhalten).';

    public function handle(PrintOrderService $service): int {
        $purged = $service->purgeExpiredFiles();
        $this->info("Bereinigt: {$purged} Druckaufträge mit abgelaufener Löschfrist.");

        return self::SUCCESS;
    }
}
