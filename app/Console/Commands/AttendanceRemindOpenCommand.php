<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceRemindOpenCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Enums\Notification\NotificationEvent;
use App\Models\Platform\User;
use App\Models\Time\Attendance;
use App\Services\Notification\NotificationDispatcher;
use App\Support\CarbonFmt;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Abend-Erinnerung bei offener Stempelung (MVP-803, Entscheid P2-04): Wer
 * vergessen hat auszustempeln, erfährt es am selben Abend — nicht erst am
 * nächsten Tag durch das automatische Schließen. Eine Erinnerung je Stempelung.
 */
class AttendanceRemindOpenCommand extends Command {
    protected $signature = 'attendance:remind-open';

    protected $description = 'Erinnert Personen, deren Stempelung seit mehreren Stunden offen ist.';

    public function handle(NotificationDispatcher $dispatcher): int {
        $threshold = Carbon::now()->subMinutes(max(1, (int) config('attendance.open_reminder_after_minutes', 480)));

        $sent = 0;
        Attendance::query()
            ->withoutGlobalScopes()
            ->whereNull('ended_at')
            ->where('started_at', '<=', $threshold)
            ->with('user')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($dispatcher, &$sent): void {
                foreach ($chunk as $attendance) {
                    /** @var Attendance $attendance */
                    $user = $attendance->user;
                    if (! $user instanceof User || $user->isDeactivated()) {
                        continue;
                    }
                    $time = $attendance->started_at !== null ? CarbonFmt::ftime($attendance->started_at) : '';
                    $sent += $dispatcher->notify(
                        NotificationEvent::AttendanceOpenReminder,
                        $attendance,
                        $user,
                        [
                            'title' => (string) __('notification.message.open_attendance_title', ['time' => $time]),
                            'title_key' => 'notification.message.open_attendance_title',
                            'title_params' => ['time' => $time],
                            'message' => (string) __('notification.message.open_attendance_body'),
                            'message_key' => 'notification.message.open_attendance_body',
                            'url' => route('attendance.index'),
                        ],
                        dedup: true,
                    );
                }
            });

        $this->info("Erinnerungen versendet: {$sent}");

        return self::SUCCESS;
    }
}
