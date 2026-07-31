<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\Diary\Status as DiaryStatus;
use App\Models\{DiaryEntry, Invoice, Project};
use Illuminate\Database\Eloquent\Builder;

/**
 * Seiteneffekte des Projekt-Speicherns (vormals Project::booted(),
 * Refactoring Welle 2, B6b): Parent-/Zyklus-Validierung, Slug-Vergabe,
 * Vererbung von Kunde/Status auf Sub-Projekte und Mitzug offener
 * Aufträge/Entwurfsrechnungen beim Kundenwechsel.
 */
class ProjectObserver {
    public function saving(Project $project): void {
        if ($project->parent_id !== null) {
            $parent = Project::query()->find($project->parent_id);
            if ($parent === null) {
                throw new \InvalidArgumentException('Parent-Projekt existiert nicht.');
            }
            if ($project->exists && (int) $parent->id === (int) $project->id) {
                throw new \InvalidArgumentException('Ein Projekt kann nicht sein eigenes Übergeordnetes Projekt sein.');
            }
            if ($project->exists && $project->isAncestorOf($parent)) {
                throw new \InvalidArgumentException('Zyklus erkannt: das gewählte Parent-Projekt ist ein Sub-Projekt dieses Projekts.');
            }
            // Sub-Projekte erben den Customer vom Parent.
            $project->customer_id = $parent->customer_id;
            $project->foreign_customer_id = $parent->foreign_customer_id;
            // Sub-Projekte dürfen kein Standardprojekt sein.
            $project->is_default = false;
        }

        if (! $project->slug) {
            $project->slug = Project::uniqueSlug($project->name, $project->customer_id);
        }

        // Kundenwechsel: (customer_id, slug) ist unique und der Slug steht nicht
        // im Formular — kollidiert er beim Zielkunden (z. B. das Auto-Projekt
        // „Wartung" existiert je Kunde), still um-sluggen statt DB-Fehler. Die
        // URL ändert sich beim Umhängen ohnehin (Route-Key beginnt mit dem
        // Kunden-Slug).
        if ($project->exists && $project->isDirty('customer_id') && $project->slug) {
            $collides = Project::query()
                ->where('customer_id', $project->customer_id)
                ->where('slug', $project->slug)
                ->where('id', '!=', $project->id)
                ->exists();
            if ($collides) {
                $project->slug = Project::uniqueSlug((string) $project->slug, $project->customer_id, (int) $project->id);
            }
        }
    }

    /** Sicherstellen, dass pro Kunde höchstens ein Standardprojekt existiert. */
    public function saved(Project $project): void {
        if ($project->wasChanged('customer_id')) {
            $project->unsetRelation('customer');
            $newCustomerId = $project->customer_id;

            // Sub-Projekte erben den neuen Kunden mit (rekursiv via Events).
            Project::query()
                ->where('parent_id', $project->id)
                ->where(function (Builder $q) use ($newCustomerId): void {
                    $q->where('customer_id', '!=', $newCustomerId)
                        ->orWhereNull('customer_id');
                })
                ->get()
                ->each(function (Project $child) use ($newCustomerId): void {
                    $child->customer_id = $newCustomerId;
                    $child->save();
                });

            // DiaryEntries mitziehen, außer finalisierte oder stornierte.
            DiaryEntry::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', [
                    DiaryStatus::Completed->value,
                    DiaryStatus::AcceptedFinal->value,
                    DiaryStatus::Invoiced->value,
                    DiaryStatus::Cancelled->value,
                ])
                ->update(['customer_id' => $newCustomerId]);

            // Rechnungen mitziehen, nur DRAFT (freigegebene/bezahlte/stornierte nicht). Über das Modell, damit
            // Ausstellungs-Guard + Auditable greifen (GobdLockGuardRuleTest); verliert das Projekt den Kunden, behalten Entwürfe den alten.
            if ($newCustomerId !== null) {
                Invoice::query()
                    ->where('project_id', $project->id)
                    ->where('status', Invoice::STATUS_DRAFT)
                    ->get()
                    ->each(function (Invoice $invoice) use ($newCustomerId): void {
                        $invoice->customer_id = $newCustomerId;
                        $invoice->save();
                    });
            }
        }

        if ($project->wasChanged('status')) {
            $newStatus = $project->status;

            // Sub-Projekte erben den Status rekursiv mit (Events feuern auch für deren Children).
            Project::query()
                ->where('parent_id', $project->id)
                ->where('status', '!=', $newStatus)
                ->get()
                ->each(function (Project $child) use ($newStatus): void {
                    $child->status = $newStatus;
                    $child->save();
                });
        }

        if (! $project->is_default || $project->customer_id === null) {
            return;
        }
        Project::query()
            ->where('customer_id', $project->customer_id)
            ->where('id', '!=', $project->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
