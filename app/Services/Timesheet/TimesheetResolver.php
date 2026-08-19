<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timesheet;

use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{Project, Timesheet, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ein offener Stundenzettel je Projekt, Nutzer und Tag.
 *
 * Vorher legten Sidebar-Anlage, Schnellanlage und Stoppuhr-Start jeweils
 * eigene Zettel an. Für denselben Einsatz konnten so zwei entstehen — die
 * gestoppten Zeiten landeten im einen, die von Hand erfassten im anderen.
 *
 * Signierte und gesperrte Zettel sind bewusst ausgenommen: nach der
 * Kundenfreigabe muss am selben Tag ein zweiter Einsatz erfassbar bleiben.
 */
class TimesheetResolver {
    /**
     * Öffnet den offenen Stundenzettel des Tages oder legt ihn an.
     *
     * @param  array<string, mixed>  $attributes  Kopfdaten aus dem Anlage-Dialog
     * @return array{0: Timesheet, 1: bool} [$timesheet, $created]
     */
    public function openOrCreate(Project $project, int $userId, CarbonImmutable $workDate, array $attributes = []): array {
        $day = $workDate->startOfDay();

        return DB::transaction(function () use ($project, $userId, $day, $attributes): array {
            // Per-User serialisieren: ohne Sperre kommen zwei parallele Requests
            // (Doppelklick, Stoppuhr + Dialog gleichzeitig) beide an der Suche
            // vorbei und legen doch zwei Zettel an.
            User::query()->whereKey($userId)->lockForUpdate()->first();

            $existing = Timesheet::query()
                ->unsigned()
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                // Carbon-Instanz statt Datums-String: der date-Cast persistiert
                // 'Y-m-d 00:00:00' — ein blanker 'Y-m-d'-String trifft auf
                // SQLite nicht (auf MySQL schon), der Fund wäre treiberabhängig.
                ->where('work_date', $day)
                ->orderBy('id')
                ->first();

            if ($existing instanceof Timesheet) {
                $this->fillBlanks($existing, $attributes);

                return [$existing, false];
            }

            /** @var Timesheet $timesheet */
            $timesheet = $project->timesheets()->create($attributes + [
                'work_date' => $day,
                'user_id' => $userId,
                'organization_id' => $project->organization_id,
                'status' => TimesheetStatus::Draft->value,
            ]);

            return [$timesheet, true];
        });
    }

    /**
     * Kopfdaten des Dialogs in einen gefundenen Zettel übernehmen — aber nur
     * in leere Felder. Was dort schon steht, ist womöglich mit dem Kunden
     * abgestimmt und darf von einer erneuten Anlage nicht überschrieben werden.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fillBlanks(Timesheet $timesheet, array $attributes): void {
        $dirty = false;

        foreach (['customer_name', 'customer_role', 'customer_email', 'notes'] as $key) {
            $value = $attributes[$key] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $current = $timesheet->{$key};
            if (is_string($current) && trim($current) !== '') {
                continue;
            }

            $timesheet->{$key} = $value;
            $dirty = true;
        }

        if ($dirty) {
            $timesheet->save();
        }
    }
}
