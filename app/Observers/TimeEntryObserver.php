<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\{TimeEntry, User};
use App\Services\Diary\OrderService;

/**
 * TimeEntry-Lebenszyklus: Auftrags-Start aus dem ersten Zeiteintrag und
 * Timesheet-Summen (Bestand) plus — seit Vollscan 2026-08-23, F14 — die
 * Fachlogik des früheren 79-Zeilen-saving-Hooks aus dem Model:
 * activity_type-Default, project-Pflicht, Minuten/Datum aus Start/Ende und
 * der Abrechnungs-Snapshot inkl. Konditions-Auflösung, 1:1 verschoben.
 */
class TimeEntryObserver {
    public function saving(TimeEntry $entry): void {

        // Default activity_type from kind / project presence.
        if (empty($entry->activity_type)) {
            $entry->activity_type = match (true) {
                $entry->project_id !== null => TimeEntryActivityType::Project,
                $entry->kind === TimeEntryKind::Travel => TimeEntryActivityType::Travel,
                $entry->kind === TimeEntryKind::Standby => TimeEntryActivityType::Standby,
                default => TimeEntryActivityType::Admin,
            };
        }

        // Enforce: project_id is required when activity_type=project.
        if ($entry->activity_type === TimeEntryActivityType::Project && $entry->project_id === null) {
            throw new \InvalidArgumentException(
                'TimeEntry with activity_type=project requires a project_id.'
            );
        }

        if ($entry->started_at && $entry->ended_at) {
            $diff = (int) $entry->started_at->diffInMinutes($entry->ended_at, false);
            $diff = max(0, $diff - (int) ($entry->break_minutes ?? 0));
            $entry->minutes = $diff;
            if (! $entry->date) {
                // Kalendertag in der Anzeige-Zeitzone, nicht UTC (wie Attendance): sonst zählt ein Eintrag um 00:30
                // lokal zum Vortag (Gleitzeit/Tagesabschluss/Monatsrechnung). started_at bleibt UTC.
                $entry->date = $entry->started_at->copy()->setTimezone(\App\Support\Tz::current())->startOfDay();
            }
        }

        // Ohne explizites billable erbt ein neuer Eintrag die effektive
        // Projekt-Einstellung (Parent-Kette → Kunde). Muss vor der
        // Snapshot-Berechnung stehen: ein fehlendes Attribut zählte dort
        // sonst als nicht abrechenbar (rate = 0), obwohl das DB-Default
        // true ist.
        if (! $entry->exists && ! array_key_exists('billable', $entry->getAttributes())) {
            $entry->billable = $entry->project?->effectiveBillable() ?? true;
        }

        // Recalculate billing snapshot whenever a relevant field changes.
        // date/started_at/activity_category_id gehören dazu, weil Kunden-
        // konditionen (Feature 098) Tagtyp- und Kategorie-abhängig sind.
        if ($entry->isDirty([
            'minutes',
            'billable',
            'hourly_rate',
            'fixed_rate',
            'project_id',
            'task_id',
            'user_id',
            'date',
            'started_at',
            'activity_category_id',
            'billing_travel_manual',
        ]) || ! $entry->exists) {
            if (
                $entry->hourlyRateWasOverridden()
                && $entry->hourly_rate !== null
                && ! $entry->matchesAgreementRate()
            ) {
                // Manueller Satz-Override löst den Konditions-Marker ab (E2)
                // — nicht aber eine Satzpflege, die denselben Konditions-
                // satz neu einträgt (reapplyRates); sonst verlöre jede
                // Satzänderung ihren Nachweis.
                $entry->customer_billing_rate_id = null;
            } elseif (
                $entry->customer_billing_rate_id !== null
                && $entry->isDirty(['date', 'started_at', 'activity_category_id', 'project_id'])
            ) {
                // Konditions-Snapshot neu auflösen: Tagtyp (Sa→So), Kategorie
                // oder Kunde können sich geändert haben. Manuelle Overrides
                // (FK=NULL) bleiben unangetastet.
                $entry->hourly_rate = null;
                $entry->customer_billing_rate_id = null;
            }
            $entry->applyRateSnapshot();
        }
    }

    public function created(TimeEntry $entry): void {
        if ($entry->diary_entry_id === null) {
            return;
        }

        $actor = $entry->user;
        $diaryEntry = $entry->diaryEntry;
        if ($actor instanceof User && $diaryEntry !== null) {
            app(OrderService::class)->startFromTimeEntry($diaryEntry, $actor);
        }
    }

    public function saved(TimeEntry $entry): void {
        $entry->timesheet?->recalcTotals();
    }

    public function deleted(TimeEntry $entry): void {
        $entry->timesheet?->recalcTotals();
    }
}
