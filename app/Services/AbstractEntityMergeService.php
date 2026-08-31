<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractEntityMergeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Gemeinsamer Kern der Entitäts-Merge-Services (Kunde/Projekt): hängt
 * abhängige Datensätze per Bulk-UPDATE um (Skalar-/Pivot-/Morph-Tabellen,
 * externe Referenzen inkl. Alias-Kollisionsbehandlung, Tags) und füllt leere
 * Ziel-Felder aus der Quelle auf. Subklassen liefern Tabellenlisten,
 * FK-Spalte und Fillables; entitätsspezifisch bleiben Vorbedingungen, der
 * merge()-Ablauf und Spezialfälle (Kinder, Defaults, Uniquify-Spalten).
 */
abstract class AbstractEntityMergeService {
    /**
     * Umgehängte Zeilen je Tabelle, gesammelt während eines Merges.
     *
     * @var array<string, int>
     */
    protected array $repointed = [];

    /** FK-Spaltenname der Entität in abhängigen Tabellen (z. B. customer_id). */
    abstract protected function foreignKeyColumn(): string;

    /**
     * Tabellen, die die FK-Spalte führen, aber bewusst **nicht** umgehängt
     * werden — Tabelle => Begründung.
     *
     * Die Zieltabellen werden aus dem Schema abgeleitet, nicht aufgezählt
     * (Sicherheitsscan 2026-08-23, S-33): eine handgepflegte Positivliste
     * veraltet mit jeder Migration still, und weil die Quelle am Ende hart
     * gelöscht wird, reißt `ON DELETE CASCADE` die vergessenen Zeilen mit.
     * Gefunden wurden so 79 nicht erfasste Tabellen, 22 davon mit CASCADE.
     * Eine Ausnahmeliste veraltet nicht: was neu dazukommt, wird umgehängt.
     *
     * @return array<string, string>
     */
    protected function skippedTables(): array {
        return [];
    }

    /**
     * Tabellen, die die Subklasse selbst behandelt (eigene Reihenfolge,
     * Uniquify-Schritt) und die deshalb nicht zusätzlich generisch laufen.
     *
     * @return list<string>
     */
    protected function separatelyHandledTables(): array {
        return [];
    }

    /**
     * Alle Tabellen mit direkter FK-Spalte, abzüglich Pivots (eigener
     * Unique-Abgleich), Ausnahmen und selbst behandelter Tabellen.
     *
     * @return list<string>
     */
    protected function scalarTables(): array {
        $skip = array_merge(
            array_keys($this->skippedTables()),
            array_keys($this->pivotTables()),
            $this->separatelyHandledTables(),
        );

        return array_values(array_diff(
            self::tablesWithColumn($this->foreignKeyColumn()),
            $skip,
        ));
    }

    /**
     * Alle Tabellen des Schemas, die eine Spalte dieses Namens führen.
     *
     * @return list<string>
     */
    public static function tablesWithColumn(string $column): array {
        $tables = [];
        foreach (Schema::getTables() as $table) {
            $name = is_array($table) ? ($table['name'] ?? '') : (string) $table;
            if ($name === '') {
                continue;
            }
            foreach (Schema::getColumns($name) as $col) {
                if (($col['name'] ?? null) === $column) {
                    $tables[] = $name;
                    break;
                }
            }
        }

        sort($tables);

        return $tables;
    }

    /**
     * Polymorphe Tabellen (Tabelle => [type-Spalte, id-Spalte]).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    abstract protected function morphTables(): array;

    /**
     * Pivot-Tabellen (Tabelle => Partner-Spalte mit Unique-Index).
     *
     * @return array<string, string>
     */
    protected function pivotTables(): array {
        return [];
    }

    /**
     * Felder, die — sofern beim Ziel leer — aus der Quelle übernommen werden.
     *
     * @return list<string>
     */
    abstract protected function fillableFromSource(): array;

    /** Hook für entitätsspezifische Feld-Logik vor dem Speichern (z. B. Default-Flag). */
    protected function mergeEntitySpecificFields(Model $source, Model $target): void {}

    protected function repointScalarTables(int $sourceId, int $targetId): void {
        $fk = $this->foreignKeyColumn();
        $tables = $this->scalarTables();

        $this->assertNoUniqueCollisions($tables, $sourceId, $targetId);

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $fk)) {
                continue;
            }
            $moved = DB::table($table)->where($fk, $sourceId)->update([$fk => $targetId]);
            if ($moved > 0) {
                $this->repointed[$table] = ($this->repointed[$table] ?? 0) + $moved;
            }
        }
    }

    /**
     * Bricht ab, wenn das Umhängen einen zusammengesetzten Unique-Index
     * verletzen würde (z. B. `UNIQUE(project_id, user_id, open_work_date)`:
     * dieselbe Person hat in beiden Projekten einen offenen Stundenzettel).
     *
     * Ohne diese Prüfung liefe der Bulk-UPDATE in einen SQL-Fehler 1062 —
     * eine Meldung, mit der niemand etwas anfangen kann. Stille Auflösung wäre
     * schlechter als der Abbruch: welche der beiden Zeilen gilt, ist eine
     * fachliche Frage. Ausgenommen sind Pivots (reine Zuordnung, Quellzeile
     * redundant) und Tabellen mit Uniquify-Hook — beide lösen die Kollision
     * bewusst auf und laufen nicht über diesen Pfad.
     *
     * @param  list<string>  $tables
     */
    protected function assertNoUniqueCollisions(array $tables, int $sourceId, int $targetId): void {
        $fk = $this->foreignKeyColumn();
        $conflicts = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $fk)) {
                continue;
            }

            foreach (Schema::getIndexes($table) as $index) {
                if (($index['unique'] ?? false) !== true) {
                    continue;
                }

                $columns = array_values($index['columns'] ?? []);
                if (count($columns) < 2 || ! in_array($fk, $columns, true)) {
                    continue;
                }

                $others = array_values(array_diff($columns, [$fk]));
                // Self-Join statt OR-Kette: NULL == NULL ist im JOIN falsch —
                // genau wie im Unique-Index, wo mehrere NULL erlaubt bleiben.
                $count = DB::table("{$table} as src")
                    ->join("{$table} as tgt", function ($join) use ($others, $fk, $targetId): void {
                        foreach ($others as $column) {
                            $join->on("src.{$column}", '=', "tgt.{$column}");
                        }
                        $join->where("tgt.{$fk}", '=', $targetId);
                    })
                    ->where("src.{$fk}", $sourceId)
                    ->count();

                if ($count > 0) {
                    $conflicts[$table] = ($conflicts[$table] ?? 0) + $count;
                }
            }
        }

        if ($conflicts !== []) {
            $detail = implode(', ', array_map(
                static fn (string $table, int $n): string => "{$table} ({$n})",
                array_keys($conflicts),
                array_values($conflicts),
            ));

            throw new \InvalidArgumentException(
                (string) __('merge.errors.unique_collision', ['tables' => $detail])
            );
        }
    }

    protected function repointPivots(int $sourceId, int $targetId): void {
        $fk = $this->foreignKeyColumn();
        foreach ($this->pivotTables() as $table => $partnerColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $fk)) {
                continue;
            }

            // Partner, die das Ziel bereits trägt, würden den Unique-Index verletzen → Quell-Zeilen dazu vorab löschen.
            $targetPartners = DB::table($table)
                ->where($fk, $targetId)
                ->pluck($partnerColumn)->all();

            if ($targetPartners !== []) {
                DB::table($table)
                    ->where($fk, $sourceId)
                    ->whereIn($partnerColumn, $targetPartners)
                    ->delete();
            }

            DB::table($table)->where($fk, $sourceId)->update([$fk => $targetId]);
        }
    }

    protected function repointExternalReferences(string $morph, int $sourceId, int $targetId): void {
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
                // Ziel hat bereits eine Primär-Referenz für dieses Plugin/diesen Typ
                // (Unique-Index). Die abweichende Quell-Fremd-ID als Alias aufs Ziel
                // sichern, damit künftige Importe mit dem alten Schlüssel ohne
                // Inbox-Umweg direkt landen.
                $this->writeAlias($morph, $targetId, $ref);
                DB::table('external_references')->where('id', $ref->id)->delete();
                continue;
            }

            DB::table('external_references')->where('id', $ref->id)->update(['referenceable_id' => $targetId]);
        }
    }

    /**
     * Bestehende Aliase der Quelle (aus früheren Merges) auf das Ziel umhängen,
     * damit Alias-Ketten über mehrere Zusammenführungen gültig bleiben.
     * Kollidierende Quell-Aliase (gleiche Fremd-ID) werden verworfen (Ziel gewinnt).
     */
    protected function repointAliases(string $morph, int $sourceId, int $targetId): void {
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

    protected function repointMorphTables(string $morph, int $sourceId, int $targetId): void {
        foreach ($this->morphTables() as $table => [$typeCol, $idCol]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $typeCol)) {
                continue;
            }
            DB::table($table)
                ->where($typeCol, $morph)
                ->where($idCol, $sourceId)
                ->update([$idCol => $targetId]);
        }
    }

    protected function repointTaggables(string $morph, int $sourceId, int $targetId): void {
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
     * Macht Werte einer Spalte in einer Kind-Tabelle vor dem Umhängen eindeutig
     * (zusammengesetzter Unique-Index FK+Spalte): kollidierende Quell-Werte
     * erhalten ein numerisches Suffix (-2, -3, …).
     */
    protected function uniquifyChildColumn(string $table, string $column, int $sourceId, int $targetId): void {
        $fk = $this->foreignKeyColumn();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $targetValues = DB::table($table)->where($fk, $targetId)->pluck($column)->all();
        $taken = array_flip(array_map('strval', $targetValues));

        $sourceRows = DB::table($table)->where($fk, $sourceId)->get(['id', $column]);
        foreach ($sourceRows as $row) {
            $value = (string) $row->{$column};
            if ($value === '' || ! isset($taken[$value])) {
                $taken[$value] = true;
                continue;
            }
            $i = 2;
            do {
                $candidate = $value . '-' . $i++;
            } while (isset($taken[$candidate]));
            $taken[$candidate] = true;
            DB::table($table)->where('id', $row->id)->update([$column => $candidate]);
        }
    }

    /**
     * Füllt leere Ziel-Felder aus der Quelle, wendet explizite Overrides an und
     * ruft den entitätsspezifischen Hook vor dem Speichern auf.
     *
     * @param  array<string, mixed>  $fieldOverrides
     */
    protected function mergeFields(Model $source, Model $target, array $fieldOverrides): void {
        $fillable = $this->fillableFromSource();

        foreach ($fillable as $field) {
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
            if (in_array($field, $fillable, true)) {
                $target->setAttribute($field, $value);
            }
        }

        $this->mergeEntitySpecificFields($source, $target);

        if ($target->isDirty()) {
            $target->save();
        }
    }

    /**
     * Hält den Merge als ein Ereignis am Ziel fest.
     *
     * Das Umhängen läuft als Bulk-UPDATE über den Query Builder — an den
     * Modell-Ereignissen und damit an den GoBD-Sperren vorbei. Für die
     * Aufbewahrung ist das vertretbar (die eingefrorenen Partei-Daten einer
     * ausgestellten Rechnung bleiben unangetastet, nur die Zuordnung wandert),
     * aber nur, solange nachvollziehbar bleibt, **dass** Belege einem anderen
     * Stammsatz zugeordnet wurden und wie vielen (Sicherheitsscan S-33).
     */
    protected function auditMerge(Model $source, Model $target): void {
        if (! method_exists($target, 'audit')) {
            return;
        }

        ksort($this->repointed);

        $target->audit('merged', [
            'source_id' => $source->getKey(),
            'source_label' => (string) ($source->getAttribute('name') ?? $source->getAttribute('title') ?? ''),
            'repointed' => $this->repointed,
        ]);
    }
}
