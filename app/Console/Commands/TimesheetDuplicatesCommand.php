<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetDuplicatesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Models\Timesheet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Listet Tage, an denen mehrere OFFENE Stundenzettel für dasselbe Projekt und
 * denselben Nutzer existieren — Altlast aus der Zeit, als Sidebar-Anlage und
 * Stoppuhr-Start getrennte Zettel erzeugten.
 *
 * Nur Bericht, kein Eingriff: welcher Zettel der führende ist, hängt an
 * Kundendaten und Unterschrift und ist nichts für eine Automatik. Der
 * Unique-Index aus 2027_01_17_100000 verlangt, dass diese Fälle vorher
 * bereinigt sind.
 */
class TimesheetDuplicatesCommand extends Command {
    protected $signature = 'timesheets:duplicates';

    protected $description = 'Zeigt Tage mit mehreren offenen Stundenzetteln je Projekt und Nutzer.';

    public function handle(): int {
        $groups = DB::table('timesheets')
            ->selectRaw('project_id, user_id, work_date, COUNT(*) as total')
            ->whereIn('status', ['draft', 'submitted'])
            ->whereNotNull('project_id')
            ->groupBy('project_id', 'user_id', 'work_date')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('work_date')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('Keine doppelten offenen Stundenzettel gefunden.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $group) {
            $sheets = Timesheet::query()
                ->with(['project:id,name', 'user:id,name'])
                ->withCount('entries')
                ->where('project_id', $group->project_id)
                ->where('user_id', $group->user_id)
                ->where('work_date', $group->work_date)
                ->whereIn('status', ['draft', 'submitted'])
                ->orderBy('id')
                ->get();

            foreach ($sheets as $index => $sheet) {
                $rows[] = [
                    $index === 0 ? $sheet->work_date->toDateString() : '',
                    $index === 0 ? (string) $sheet->project?->name : '',
                    $index === 0 ? (string) $sheet->user?->name : '',
                    (string) $sheet->id,
                    $sheet->status->value,
                    (string) $sheet->entries_count,
                    (string) $sheet->totals_minutes,
                ];
            }
        }

        $this->table(
            ['Datum', 'Projekt', 'Nutzer', 'Zettel-ID', 'Status', 'Zeilen', 'Minuten'],
            $rows,
        );
        $this->warn(
            $groups->count() . ' Tag(e) betroffen. Zusammenlegen von Hand: Zeilen und Material auf den '
                . 'führenden Zettel umhängen, den leeren löschen — danach greift der Unique-Index.'
        );

        return self::SUCCESS;
    }
}
