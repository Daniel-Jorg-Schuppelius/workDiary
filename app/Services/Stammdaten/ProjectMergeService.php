<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectMergeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Führt zwei lokale Projekt-Datensätze zusammen (Dubletten-Bereinigung, z. B.
 * mehrfach angelegte „Wartung"-Projekte nach dem Toggl-Import). Alle abhängigen
 * Datensätze (Zeiten, Aufträge, Aufgaben, Meilensteine, Rechnungen, externe
 * Referenzen …) werden vom Quell- auf das Ziel-Projekt umgehängt, leere
 * Ziel-Felder aus der Quelle aufgefüllt, das Quell-Projekt anschließend hart
 * gelöscht. Umhäng-Kern siehe {@see AbstractEntityMergeService}.
 *
 * Mandanten-/Konsistenz-Garantie: Quelle und Ziel müssen zur selben Organisation
 * UND zum selben Kunden gehören und dürfen nicht identisch oder hierarchisch
 * verwandt sein. Die Kunden-Gleichheit hält die mitgezogenen Zeiten/Aufträge
 * konsistent (deren customer_id passt weiterhin zum Projekt-Kunden). Soll ein
 * Projekt zu einem anderen Kunden, ist das vorher über die Projekt-Bearbeitung
 * zu ändern (die den Kunden sauber kaskadiert).
 *
 * Kollisionsbehandlung:
 *  - external_references trägt einen Unique-Index über
 *    (plugin_id, external_type, referenceable_type, referenceable_id);
 *    kollidierende Quell-Referenzen werden verworfen (Ziel gewinnt). Dadurch
 *    zeigt z. B. der Toggl-Schlüssel „client|project" künftig auf das Ziel —
 *    Folgeimporte landen automatisch richtig.
 *  - taggables hat den Primärschlüssel (tag_id, taggable_id, taggable_type);
 *    Tags, die das Ziel bereits trägt, werden nicht doppelt umgehängt.
 *  - project_team/project_user tragen Unique-Indizes; bereits am Ziel
 *    bestehende Zuordnungen werden vor dem Umhängen entfernt (dedupliziert).
 */
class ProjectMergeService extends AbstractEntityMergeService {
    /**
     * Pivot-Tabellen (project_id + Partner-Spalte mit Unique-Index). Vor dem
     * Umhängen werden Zeilen entfernt, deren Partner das Ziel bereits trägt.
     *
     * @var array<string, string>
     */
    private const PIVOT_TABLES = [
        'project_team' => 'team_id',
        'project_user' => 'user_id',
    ];

    /**
     * Polymorphe Tabellen, deren Zeilen auf ein Projekt zeigen können
     * (type-Spalte => id-Spalte). Keine eigenen Unique-Indizes → Bulk-UPDATE.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MORPH_TABLES = [
        'communication_notes' => ['notable_type', 'notable_id'],
        'attachments' => ['attachable_type', 'attachable_id'],
        'pending_external_conflicts' => ['referenceable_type', 'referenceable_id'],
    ];

    /**
     * Felder, die — sofern beim Ziel leer — aus der Quelle übernommen werden.
     *
     * @var list<string>
     */
    private const FILLABLE_FROM_SOURCE = [
        'number', 'description', 'invoice_text', 'color', 'foreign_customer_id',
        'hourly_rate', 'internal_rate', 'time_budget', 'budget', 'budget_type',
        'billing_increment_minutes', 'billing_grouping_gap_minutes',
        'starts_on', 'ends_on',
    ];

    protected function foreignKeyColumn(): string {
        return 'project_id';
    }

    protected function morphTables(): array {
        return self::MORPH_TABLES;
    }

    protected function pivotTables(): array {
        return self::PIVOT_TABLES;
    }

    protected function fillableFromSource(): array {
        return self::FILLABLE_FROM_SOURCE;
    }

    /**
     * Hängt alle Daten von $source auf $target um und löscht $source.
     *
     * @param  array<string, mixed>  $fieldOverrides  Feldwerte, die unabhängig
     *         vom „leer"-Kriterium den Ziel-Wert setzen (UI-Feldauswahl).
     */
    public function merge(Project $source, Project $target, array $fieldOverrides = []): void {
        if ($source->getKey() === $target->getKey()) {
            throw new InvalidArgumentException('Quelle und Ziel dürfen nicht identisch sein.');
        }
        if ($source->organization_id !== $target->organization_id) {
            throw new InvalidArgumentException('Projekte gehören zu unterschiedlichen Organisationen.');
        }
        if ($source->customer_id !== $target->customer_id) {
            throw new InvalidArgumentException('Projekte gehören zu unterschiedlichen Kunden. Bitte zuerst den Kunden des Projekts angleichen.');
        }
        if ($source->isAncestorOf($target) || $target->isAncestorOf($source)) {
            throw new InvalidArgumentException('Projekte sind hierarchisch verwandt (Eltern/Kind) und können nicht zusammengeführt werden.');
        }

        $morph = $source->getMorphClass();
        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        DB::transaction(function () use ($source, $target, $sourceId, $targetId, $morph, $fieldOverrides): void {
            $this->repointed = [];
            $this->repointChildren($sourceId, $targetId);
            $this->repointScalarTables($sourceId, $targetId);
            $this->repointPivots($sourceId, $targetId);
            $this->repointExternalReferences($morph, $sourceId, $targetId);
            $this->repointAliases($morph, $sourceId, $targetId);
            $this->repointMorphTables($morph, $sourceId, $targetId);
            $this->repointTaggables($morph, $sourceId, $targetId);
            $this->mergeFields($source, $target, $fieldOverrides);

            $this->auditMerge($source, $target);

            // Hartes Löschen (Kinder/Refs bereits umgehängt). Über das Modell, damit der Audit-Log „deleted" festhält.
            $source->delete();
        });
    }

    /**
     * Sub-Projekte des Quell-Projekts auf das Ziel umhängen. Da Quelle und Ziel
     * denselben Kunden haben, bleiben Slug-Uniqueness (customer_id, slug) und die
     * geerbten Kundenfelder der Kinder unberührt.
     */
    private function repointChildren(int $sourceId, int $targetId): void {
        DB::table('projects')->where('parent_id', $sourceId)->update(['parent_id' => $targetId]);
    }

    /**
     * War das Quell-Projekt das Standardprojekt des Kunden, erbt das Ziel den
     * Status, damit der Kunde nicht ohne Standardprojekt zurückbleibt.
     */
    protected function mergeEntitySpecificFields(Model $source, Model $target): void {
        if (! $source instanceof Project || ! $target instanceof Project) {
            return;
        }

        if ($source->is_default && ! $target->is_default && $target->customer_id !== null) {
            $target->is_default = true;
        }

        // Schlüsselwörter (MVP-483) werden vereinigt statt ersetzt: beide Listen
        // beschreiben Texte, die künftig auf dem Ziel landen sollen.
        $merged = array_values(array_unique(array_merge(
            is_array($target->keywords) ? $target->keywords : [],
            is_array($source->keywords) ? $source->keywords : [],
        )));
        if ($merged !== []) {
            $target->keywords = array_slice($merged, 0, 20);
        }
    }
}
