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
use Illuminate\Support\Facades\{DB, Schema};
use InvalidArgumentException;

/**
 * Führt zwei lokale Projekt-Datensätze zusammen (Dubletten-Bereinigung, z. B.
 * mehrfach angelegte „Wartung"-Projekte nach dem Toggl-Import). Alle abhängigen
 * Datensätze (Zeiten, Aufträge, Aufgaben, Meilensteine, Rechnungen, externe
 * Referenzen …) werden vom Quell- auf das Ziel-Projekt umgehängt, leere
 * Ziel-Felder aus der Quelle aufgefüllt, das Quell-Projekt anschließend hart
 * gelöscht.
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
class ProjectMergeService {
    /**
     * Tabellen mit direkter `project_id`-Spalte ohne Unique-Konflikt auf
     * project_id → einfacher Bulk-UPDATE bei Bedarf (Schema-Check). Die
     * Hierarchie (projects.parent_id) und die Pivots werden gesondert behandelt.
     *
     * @var list<string>
     */
    private const PROJECT_ID_TABLES = [
        'diary_entries',
        'time_entries',
        'tasks',
        'milestones',
        'timesheets',
        'project_billing_rules',
        'invoices',
        'expenses',
        'travel_logs',
        'per_diem_trips',
        'recurrence_rules',
        'service_tickets',
        'service_orders',
        'manufacturing_orders',
        'bill_of_quantities',
        'customer_geofences',
        'location_pending_entries',
    ];

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
            $this->repointChildren($sourceId, $targetId);
            $this->repointScalarTables($sourceId, $targetId);
            $this->repointPivots($sourceId, $targetId);
            $this->repointExternalReferences($morph, $sourceId, $targetId);
            $this->repointAliases($morph, $sourceId, $targetId);
            $this->repointMorphTables($morph, $sourceId, $targetId);
            $this->repointTaggables($morph, $sourceId, $targetId);
            $this->mergeFields($source, $target, $fieldOverrides);

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

    private function repointScalarTables(int $sourceId, int $targetId): void {
        foreach (self::PROJECT_ID_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'project_id')) {
                continue;
            }
            DB::table($table)->where('project_id', $sourceId)->update(['project_id' => $targetId]);
        }
    }

    private function repointPivots(int $sourceId, int $targetId): void {
        foreach (self::PIVOT_TABLES as $table => $partnerColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'project_id')) {
                continue;
            }

            // Partner, die das Ziel bereits trägt, würden den Unique-Index verletzen → Quell-Zeilen dazu vorab löschen.
            $targetPartners = DB::table($table)
                ->where('project_id', $targetId)
                ->pluck($partnerColumn)->all();

            if ($targetPartners !== []) {
                DB::table($table)
                    ->where('project_id', $sourceId)
                    ->whereIn($partnerColumn, $targetPartners)
                    ->delete();
            }

            DB::table($table)->where('project_id', $sourceId)->update(['project_id' => $targetId]);
        }
    }

    private function repointExternalReferences(string $morph, int $sourceId, int $targetId): void {
        if (! Schema::hasTable('external_references')) {
            return;
        }

        $sourceRefs = DB::table('external_references')
            ->where('referenceable_type', $morph)
            ->where('referenceable_id', $sourceId)
            ->get(['id', 'organization_id', 'plugin_id', 'external_type', 'external_id']);

        foreach ($sourceRefs as $ref) {
            $collision = DB::table('external_references')
                ->where('referenceable_type', $morph)
                ->where('referenceable_id', $targetId)
                ->where('plugin_id', $ref->plugin_id)
                ->where('external_type', $ref->external_type)
                ->exists();

            if ($collision) {
                // Ziel hat bereits eine Primär-Referenz für dieses Plugin/diesen Typ (Unique-Index). Abweichende
                // Quell-Fremd-ID (z. B. anderer Toggl-Projektname) als Alias aufs Ziel sichern, damit alte Schlüssel direkt landen.
                $this->writeAlias($morph, $targetId, $ref);
                DB::table('external_references')->where('id', $ref->id)->delete();
                continue;
            }

            DB::table('external_references')->where('id', $ref->id)->update(['referenceable_id' => $targetId]);
        }
    }

    /**
     * Bereits bestehende Aliase des Quell-Projekts (aus früheren Merges) auf das
     * Ziel umhängen, damit Alias-Ketten über mehrere Zusammenführungen gültig
     * bleiben. Würde ein Alias mit dem Ziel kollidieren (gleiche Fremd-ID), wird
     * die Quell-Zeile verworfen (Ziel-Alias gewinnt).
     */
    private function repointAliases(string $morph, int $sourceId, int $targetId): void {
        if (! Schema::hasTable('external_reference_aliases')) {
            return;
        }

        $targetKeys = DB::table('external_reference_aliases')
            ->where('referenceable_type', $morph)
            ->where('referenceable_id', $targetId)
            ->get(['plugin_id', 'external_type', 'external_id'])
            ->map(fn($a): string => $a->plugin_id . '|' . $a->external_type . '|' . $a->external_id)
            ->all();

        $sourceAliases = DB::table('external_reference_aliases')
            ->where('referenceable_type', $morph)
            ->where('referenceable_id', $sourceId)
            ->get(['id', 'plugin_id', 'external_type', 'external_id']);

        foreach ($sourceAliases as $alias) {
            $key = $alias->plugin_id . '|' . $alias->external_type . '|' . $alias->external_id;
            if (in_array($key, $targetKeys, true)) {
                DB::table('external_reference_aliases')->where('id', $alias->id)->delete();
                continue;
            }
            DB::table('external_reference_aliases')->where('id', $alias->id)->update(['referenceable_id' => $targetId]);
        }
    }

    /**
     * Schreibt/aktualisiert einen Alias (Fremd-ID → Ziel). Idempotent über den
     * Unique-Schlüssel (organization_id, plugin_id, external_type, external_id).
     */
    private function writeAlias(string $morph, int $targetId, \stdClass $ref): void {
        if (! Schema::hasTable('external_reference_aliases')) {
            return;
        }

        $now = now();
        $exists = DB::table('external_reference_aliases')
            ->where('organization_id', $ref->organization_id)
            ->where('plugin_id', $ref->plugin_id)
            ->where('external_type', $ref->external_type)
            ->where('external_id', $ref->external_id)
            ->first(['id']);

        if ($exists !== null) {
            DB::table('external_reference_aliases')->where('id', $exists->id)->update([
                'referenceable_type' => $morph,
                'referenceable_id' => $targetId,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('external_reference_aliases')->insert([
            'organization_id' => $ref->organization_id,
            'plugin_id' => $ref->plugin_id,
            'external_type' => $ref->external_type,
            'external_id' => $ref->external_id,
            'referenceable_type' => $morph,
            'referenceable_id' => $targetId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function repointMorphTables(string $morph, int $sourceId, int $targetId): void {
        foreach (self::MORPH_TABLES as $table => [$typeCol, $idCol]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $typeCol)) {
                continue;
            }
            DB::table($table)
                ->where($typeCol, $morph)
                ->where($idCol, $sourceId)
                ->update([$idCol => $targetId]);
        }
    }

    private function repointTaggables(string $morph, int $sourceId, int $targetId): void {
        if (! Schema::hasTable('taggables')) {
            return;
        }

        // Tags, die das Ziel bereits trägt, nicht doppelt umhängen (PK tag_id+taggable_id+taggable_type).
        $targetTagIds = DB::table('taggables')
            ->where('taggable_type', $morph)
            ->where('taggable_id', $targetId)
            ->pluck('tag_id')->all();

        if ($targetTagIds !== []) {
            DB::table('taggables')
                ->where('taggable_type', $morph)
                ->where('taggable_id', $sourceId)
                ->whereIn('tag_id', $targetTagIds)
                ->delete();
        }

        DB::table('taggables')
            ->where('taggable_type', $morph)
            ->where('taggable_id', $sourceId)
            ->update(['taggable_id' => $targetId]);
    }

    /**
     * Füllt leere Ziel-Felder aus der Quelle, wendet explizite Overrides an und
     * überträgt das Standard-Flag, falls die Quelle (das gelöschte Projekt) das
     * Standardprojekt des Kunden war.
     *
     * @param  array<string, mixed>  $fieldOverrides
     */
    private function mergeFields(Project $source, Project $target, array $fieldOverrides): void {
        foreach (self::FILLABLE_FROM_SOURCE as $field) {
            $current = $target->getAttribute($field);
            $isEmpty = $current === null || $current === '' || $current === [];
            if ($isEmpty) {
                $sourceValue = $source->getAttribute($field);
                if ($sourceValue !== null && $sourceValue !== '' && $sourceValue !== []) {
                    $target->setAttribute($field, $sourceValue);
                }
            }
        }

        foreach ($fieldOverrides as $field => $value) {
            if (in_array($field, self::FILLABLE_FROM_SOURCE, true)) {
                $target->setAttribute($field, $value);
            }
        }

        // War das Quell-Projekt das Standardprojekt des Kunden, erbt das Ziel den
        // Status, damit der Kunde nicht ohne Standardprojekt zurückbleibt.
        if ($source->is_default && ! $target->is_default && $target->customer_id !== null) {
            $target->is_default = true;
        }

        if ($target->isDirty()) {
            $target->save();
        }
    }
}
