<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceBackfillCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Models\{Attendance, TimeEntry};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills attendance sessions from existing TimeEntries so that the new
 * "attendance is authoritative" world view has historical coverage.
 *
 * For each (user_id, date) combination with at least one TimeEntry that has
 * started_at/ended_at, creates one Attendance spanning min(started_at) to
 * max(ended_at) and links the involved TimeEntries to it.
 *
 * Idempotent: skips users/dates that already have an attendance record.
 */
class AttendanceBackfillCommand extends Command {
    protected $signature = 'attendance:backfill {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Erzeugt Anwesenheits-Sessions aus vorhandenen Zeiteinträgen.';

    public function handle(): int {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $linked = 0;

        $rows = DB::table((new TimeEntry)->getTable())
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->whereNull('attendance_id')
            ->selectRaw('user_id, date, organization_id, MIN(started_at) AS min_start, MAX(ended_at) AS max_end')
            ->groupBy('user_id', 'date', 'organization_id')
            ->get();

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $row) {
            $existing = Attendance::query()
                ->where('user_id', $row->user_id)
                ->where('date', $row->date)
                ->first();

            if ($existing) {
                $bar->advance();

                continue;
            }

            $start = CarbonImmutable::parse((string) $row->min_start);
            $end = CarbonImmutable::parse((string) $row->max_end);

            if ($dryRun) {
                $created++;
                $bar->advance();

                continue;
            }

            DB::transaction(function () use ($row, $start, $end, &$created, &$linked): void {
                $attendance = Attendance::create([
                    'organization_id' => $row->organization_id,
                    'user_id' => $row->user_id,
                    'started_at' => $start,
                    'ended_at' => $end,
                    'date' => $start->startOfDay(),
                    'source' => AttendanceSource::Import->value,
                    'status' => AttendanceStatus::Closed->value,
                    'note' => 'Backfilled from existing time entries.',
                ]);

                $linked += TimeEntry::query()
                    ->where('user_id', $row->user_id)
                    ->where('date', $row->date)
                    ->whereNull('attendance_id')
                    ->update(['attendance_id' => $attendance->id]);

                $created++;
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '') . "Created {$created} attendance(s), linked {$linked} entries.");

        return self::SUCCESS;
    }
}
