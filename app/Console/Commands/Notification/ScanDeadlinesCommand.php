<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanDeadlinesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Notification;

use App\Services\Notification\DeadlineScans\{DeadlineScanOptions, DeadlineScanRegistry};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Fristen-Scanner für Benachrichtigungen & Eskalationen (MVP-018): iteriert
 * über die {@see DeadlineScanRegistry} (eine Scan-Klasse je Fachmodul, B11)
 * und summiert die versendeten Benachrichtigungen. Die Scan-Logik selbst
 * (Zwei-Phasen-Skelett C18, Dedup über das notification_dispatch_log,
 * `due_at`-Payloads für den Kalender-Kanal) liegt in
 * app/Services/Notification/DeadlineScans/.
 */
class ScanDeadlinesCommand extends Command {
    protected $signature = 'notifications:scan-deadlines
        {--due-days=3 : Vorlauf in Tagen für dueSoon-Ereignisse}
        {--expiring-days=30 : Vorlauf in Tagen für ablaufende Dokumente}';

    protected $description = 'Scannt Fristen (Offene Punkte, Folgeaktionen, Dokumente) und versendet Benachrichtigungen inkl. Eskalation.';

    public function handle(NotificationDispatcher $dispatcher, DeadlineScanRegistry $registry): int {
        $options = new DeadlineScanOptions(
            dueDays: max(1, (int) $this->option('due-days')),
            expiringDays: max(1, (int) $this->option('expiring-days')),
            info: fn(string $message) => $this->info($message),
        );

        $sent = 0;
        foreach ($registry->scans() as $scan) {
            $count = $scan->run($dispatcher, $options);
            if ($this->output->isVerbose()) {
                $this->line(sprintf('%s: %d', $scan->key(), $count));
            }
            $sent += $count;
        }

        $this->info(sprintf('%d Benachrichtigung(en) versendet.', $sent));

        return self::SUCCESS;
    }
}
