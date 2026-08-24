<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntriesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{Organization, TimeEntry};
use Carbon\CarbonInterface;

/** Erfasste Arbeitszeiten des Prüfungszeitraums (GoBD Rz. 20), nach Leistungsdatum. */
class TimeEntriesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'time_entries';
    }

    public function definition(): array {
        return [
            'file' => 'zeitnachweise.csv',
            'name' => 'Zeitnachweise',
            'description' => 'Erfasste Arbeitszeiten des Prüfungszeitraums (GoBD Rz. 20 nennt die Zeiterfassung ausdrücklich als vorlagepflichtig), nach Leistungsdatum.',
            'columns' => [
                ['name' => 'Datum', 'type' => 'date'],
                ['name' => 'Mitarbeiternummer', 'type' => 'alpha'],
                ['name' => 'Mitarbeiter', 'type' => 'alpha'],
                ['name' => 'Kunde', 'type' => 'alpha'],
                ['name' => 'Projekt', 'type' => 'alpha'],
                ['name' => 'Taetigkeit', 'type' => 'alpha'],
                ['name' => 'Beschreibung', 'type' => 'alpha'],
                ['name' => 'Dauer_Stunden', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Abrechenbar', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        TimeEntry::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('date')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with(['user:id,name,personnel_number', 'project:id,name,customer_id', 'project.customer:id,name'])
            ->orderBy('date')->orderBy('id')
            ->get()
            ->each(function (TimeEntry $entry) use (&$rows): void {
                $personnelNo = $entry->user?->personnel_number;
                if ($personnelNo === null || $personnelNo === '') {
                    $personnelNo = (string) ($entry->user_id ?? '');
                }
                $rows[] = [
                    $this->date($entry->date),
                    $this->str($personnelNo),
                    $this->str($entry->user?->name),
                    $this->str($entry->project?->customer?->name),
                    $this->str($entry->project?->name),
                    $this->str($entry->activity_type->value),
                    $this->str($entry->description),
                    $this->num($entry->minutes / 60, 2),
                    $entry->billable ? 'Ja' : 'Nein',
                ];
            });

        return $rows;
    }
}
