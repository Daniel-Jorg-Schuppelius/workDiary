<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryBillableSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Models\{Customer, InvoiceItem, Project, TimeEntry};
use App\Plugins\Support\TimeWritebackObserver;
use Illuminate\Support\Facades\DB;

/**
 * Zieht den Abrechenbar-Schalter von Kunde/Projekt auf bestehende OFFENE
 * Zeiteinträge durch: billable ist am Eintrag ein Snapshot vom
 * Erfassungszeitpunkt — ohne diesen Sync bleibt ein nachträglich auf
 * „nicht abrechenbar" gestellter Kunde in Offene Zeiten abrechenbar.
 *
 * Betroffen sind nur Projekte, die den Wert tatsächlich ERBEN (billable
 * NULL bis hinauf zur Quelle) — explizite Projekt-Übersteuerungen und deren
 * Teilbäume bleiben unberührt. Guards je Eintrag wie beim
 * Toggl-Repair-Command: exportierte, rechnungsverknüpfte und signierte
 * Einträge werden nie angefasst. Eloquent-Save pro Eintrag, damit der
 * Satz-Snapshot (rate/internal_rate) neu rechnet und Auditable protokolliert;
 * Writeback an Plugins bewusst unterdrückt (kein Outbox-Schwall).
 */
class TimeEntryBillableSyncService {
    /** @return int Anzahl angepasster Einträge (bei $apply=false: Anzahl, die angepasst würde) */
    public function syncCustomer(Customer $customer, bool $apply = true): int {
        $projects = $customer->projects()->with(['parent', 'customer'])->get();
        $affected = $projects->filter(fn(Project $p): bool => $this->inheritsFromCustomer($p))->all();

        // Kind-Projekte ohne eigene Kundenzuordnung erben über die
        // Parent-Kette — Duplikate kollabieren in syncProjects über die ID.
        foreach ([...$affected] as $project) {
            $affected = [...$affected, ...$this->inheritingDescendants($project)];
        }

        return $this->syncProjects(array_values($affected), $apply);
    }

    /** @return int Anzahl angepasster Einträge (bei $apply=false: Anzahl, die angepasst würde) */
    public function syncProject(Project $project, bool $apply = true): int {
        $project->loadMissing(['parent', 'customer']);

        return $this->syncProjects([$project, ...$this->inheritingDescendants($project)], $apply);
    }

    /** Erbt das Projekt sein billable bis hinauf vom Kunden (kein Override in der Kette)? */
    private function inheritsFromCustomer(Project $project): bool {
        if ($project->billable !== null) {
            return false;
        }
        if ($project->parent !== null) {
            return $this->inheritsFromCustomer($project->parent);
        }

        return true;
    }

    /**
     * Kind-Projekte (rekursiv), die ihr billable vom übergebenen Projekt erben.
     *
     * @return list<Project>
     */
    private function inheritingDescendants(Project $project): array {
        $result = [];
        foreach ($project->children()->with(['parent', 'customer'])->get() as $child) {
            if ($child->billable !== null) {
                continue;
            }
            $result[] = $child;
            $result = [...$result, ...$this->inheritingDescendants($child)];
        }

        return $result;
    }

    /**
     * @param  list<Project>  $projects
     */
    private function syncProjects(array $projects, bool $apply = true): int {
        if ($projects === []) {
            return 0;
        }

        $targetByProject = [];
        foreach ($projects as $project) {
            $targetByProject[(int) $project->id] = $project->effectiveBillable();
        }

        $synced = 0;
        TimeWritebackObserver::suppressed(function () use ($targetByProject, $apply, &$synced): void {
            TimeEntry::query()
                ->whereIn('project_id', array_keys($targetByProject))
                ->where('exported', false)
                ->with('timesheet')
                ->orderBy('id')
                ->chunkById(200, function ($entries) use ($targetByProject, $apply, &$synced): void {
                    foreach ($entries as $entry) {
                        $target = $targetByProject[(int) $entry->project_id] ?? null;
                        if ($target === null || (bool) $entry->billable === $target) {
                            continue;
                        }
                        if ($this->isInvoiceLinked($entry) || $entry->timesheet?->isSigned() === true) {
                            continue;
                        }

                        if ($apply) {
                            $entry->billable = $target;
                            $entry->save();
                        }
                        $synced++;
                    }
                });
        });

        return $synced;
    }

    /** Direkt-FK und Sammelpositionen-Pivot — beide zählen als abgerechnet. */
    private function isInvoiceLinked(TimeEntry $entry): bool {
        return InvoiceItem::query()->where('time_entry_id', $entry->getKey())->exists()
            || DB::table('invoice_item_time_entries')->where('time_entry_id', $entry->getKey())->exists();
    }
}
