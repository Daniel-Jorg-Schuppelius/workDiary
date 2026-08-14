{{--
  Created on   : Mon May 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _month_matrix.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Month matrix — server-side Blade-Kalender ohne Alpine.

    Frühere Eigen-Implementierung wurde durch die zentrale <x-month-calendar>
    ersetzt, sodass Schichtplan und Veranstaltungs-Kalender denselben
    Wochentag-Header, dieselbe Sonntag-/Samstag-/Feiertag-Tonalität und
    dasselbe Höhen-Verhalten haben.

    Verfügbar (aus index.blade.php / schedule controller):
      $anchor, $from, $to, $shifts, $shiftsByDate, $users, $holidays,
      $isAdmin, $openSlotsByDate, $complianceByShift

    Die komplexe Cell-Logik (Klick-Handler, Drag&Drop, Open-Slots, Compliance,
    Add-Hint) liegt im Schichtplan-Cell-Partial; die Komponente übergibt
    pro Tag $day, $items (=Shifts des Tages) und die Status-Flags.
--}}
<x-month-calendar
    :month="$from"
    :items-by-day="$shiftsByDate"
    :holidays="$holidays"
    cell-view="schedule.partials._calendar_cell"
    :cell-data="[
        'isAdmin'           => $isAdmin ?? false,
        'shiftsByDate'      => $shiftsByDate ?? collect(),
        'openSlotsByDate'   => $openSlotsByDate ?? [],
        'complianceByShift' => $complianceByShift ?? [],
        'qualificationGapByShift' => $qualificationGapByShift ?? [],
    ]"
    full-height />
