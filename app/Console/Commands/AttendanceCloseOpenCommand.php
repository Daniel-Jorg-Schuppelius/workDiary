<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceCloseOpenCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceClockService;
use Illuminate\Console\Command;

class AttendanceCloseOpenCommand extends Command {
    protected $signature = 'attendance:close-open';

    protected $description = 'Schließt automatisch vergessene offene Stempelungen (älter als max. Sitzungsdauer).';

    public function handle(AttendanceClockService $service): int {
        $count = $service->autoCloseStaleSessions();
        $this->info("Auto-closed {$count} stale attendance(s).");

        return self::SUCCESS;
    }
}
