<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Models\Article\Article;
use App\Models\Asset\Asset;
use App\Models\Contracts\CustomFieldSubject;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Fields\{CustomFieldDefinition, CustomFieldValue};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Benutzerdefinierte Felder je Organisation (MVP-868): Schema je Träger
 * ({@see CustomFieldDefinition}), Werte je Datensatz ({@see CustomFieldValue}),
 * Validierung und Wertform aus dem Feldschema-Baustein. Upload- und
 * Signaturtypen sind ausgeschlossen — es gibt keinen Dateikanal am Träger.
 */
class CustomFieldService {
    /** Listenspalten je Träger — mehr sprengt die Übersicht (Welle 4.6). */
    public const MAX_LIST_COLUMNS = 3;

    /** @var list<class-string<Model>> Träger, die eigene Felder erlauben */
    public const SUBJECTS = [Customer::class, DiaryEntry::class, Asset::class, Article::class, Project::class];

    /** @var array<string, FieldSchema> Schema je „org:alias" für die Dauer eines Requests */
    private array $schemas = [];

    public function __construct(
        private readonly FieldValidator $validator,
        private readonly FieldExtensionRegistry $extensions,
        private readonly FieldExport $export,
    ) {}

    /** @return array<string, class-string<Model>> Morph-Alias → Trägerklasse */
    public function subjects(): array {
        $subjects = [];
        foreach (self::SUBJECTS as $class) {
            $subjects[MorphMap::alias($class)] = $class;
        }

        return $subjects;
    }

    /** @param  class-string<Model>|string  $subject  Klasse oder Morph-Alias */
    public function aliasOf(string $subject): string {
        return class_exists($subject) ? MorphMap::alias($subject) : $subject;
    }

    public function definition(int $organizationId, string $alias): ?CustomFieldDefinition {
        return CustomFieldDefinition::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('subject_alias', $alias)
            ->first();
    }

    /**
     * Aktives Schema eines Trägers; leer ohne Definition oder bei
     * deaktivierter Definition.
     *
     * @param  class-string<Model>|Model|string  $subject
     */
    public function schemaFor(string|Model $subject, ?int $organizationId = null): FieldSchema {
        if ($subject instanceof Model) {
            $organizationId ??= (int) $subject->getAttribute('organization_id');
            $subject = $subject::class;
        }
        $alias = $this->aliasOf($subject);
        if ($organizationId === null || $organizationId <= 0 || ! isset($this->subjects()[$alias])) {
            return new FieldSchema([]);
        }
        $cacheKey = $organizationId . ':' . $alias;
        if (! isset($this->schemas[$cacheKey])) {
            $definition = $this->definition($organizationId, $alias);
            $this->schemas[$cacheKey] = $definition !== null && $definition->is_active ? $definition->schema : new FieldSchema([]);
        }

        return $this->schemas[$cacheKey];
    }

    /** @return array<string, list<mixed>> Regeln für `custom.<key>` */
    public function rules(FieldSchema $schema): array {
        return $this->validator->rules($schema, 'custom');
    }

    /**
     * Schema aus Dialogzeilen speichern; Version zählt bei Änderung hoch.
     *
     * @param  array<int|string, mixed>  $rows
     *
     * @throws ValidationException
     */
    public function saveDefinition(Organization $organization, string $alias, array $rows, bool $active = true): CustomFieldDefinition {
        if (! isset($this->subjects()[$alias])) {
            throw ValidationException::withMessages(['subject' => (string) __('fields.custom.validation.unknown_subject')]);
        }
        $schema = FieldSchema::fromRows($rows);
        if (count(array_filter($schema->all(), static fn (FieldDefinition $field): bool => $field->listed)) > self::MAX_LIST_COLUMNS) {
            throw ValidationException::withMessages(['fields' => (string) __('fields.custom.validation.too_many_listed', ['max' => self::MAX_LIST_COLUMNS])]);
        }
        foreach ($schema as $field) {
            if ($field->type->storesAttachment()) {
                throw ValidationException::withMessages(['fields' => (string) __('fields.custom.validation.type_not_allowed', ['label' => $field->label])]);
            }
        }
        $definition = $this->definition((int) $organization->id, $alias) ?? new CustomFieldDefinition([
            'organization_id' => $organization->id,
            'subject_alias' => $alias,
            'version' => 0,
        ]);
        $changed = ! $definition->exists || $definition->schema->toArray() !== $schema->toArray();
        $definition->schema = $schema;
        $definition->is_active = $active;
        if ($changed) {
            $definition->version = (int) $definition->version + 1;
        }
        $definition->save();
        unset($this->schemas[$organization->id . ':' . $alias]);

        return $definition;
    }

    public function setActive(CustomFieldDefinition $definition, bool $active): void {
        $definition->update(['is_active' => $active]);
        unset($this->schemas[$definition->organization_id . ':' . $definition->subject_alias]);
    }

    /**
     * Felder aus einem Branchenprofil ergänzen: nur Schlüssel, die die
     * Organisation noch nicht hat — eigene Definitionen bleiben unangetastet.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function mergeDefinition(Organization $organization, string $alias, array $rows): array {
        $incoming = FieldSchema::fromRows($rows);
        $existing = $this->definition((int) $organization->id, $alias);
        $current = $existing !== null ? $existing->schema : new FieldSchema([]);
        $fields = $current->all();
        $created = 0;
        $skipped = 0;
        foreach ($incoming as $field) {
            if ($current->has($field->key) || $field->type->storesAttachment()) {
                $skipped++;

                continue;
            }
            $fields[] = $field;
            $created++;
        }
        if ($created > 0) {
            $this->saveDefinition($organization, $alias, (new FieldSchema($fields))->toArray(), $existing !== null ? $existing->is_active : true);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Werte eines Trägers schreiben (leeres Schema entfernt den Wertesatz).
     *
     * @param  array<string, mixed>  $input
     */
    public function sync(Model&CustomFieldSubject $subject, array $input): ?CustomFieldValue {
        $schema = $this->schemaFor($subject);
        $definition = $this->definition((int) $subject->getAttribute('organization_id'), MorphMap::alias($subject::class));
        if ($schema->isEmpty()) {
            $subject->customFieldValue()->delete();
            $subject->unsetRelation('customFieldValue');

            return null;
        }
        $values = FieldValues::normalize($schema, $input, $this->extensions);
        $row = $subject->customFieldValue()->updateOrCreate([], [
            'organization_id' => $subject->getAttribute('organization_id'),
            'values' => $values,
            'schema_version' => $definition !== null ? (int) $definition->version : 1,
        ]);
        $subject->setRelation('customFieldValue', $row);

        return $row;
    }

    /**
     * Anzeigetexte der belegten Felder (Suche, Export).
     *
     * @return list<string>
     */
    public function texts(Model&CustomFieldSubject $subject): array {
        $schema = $this->schemaFor($subject);
        $values = $subject->customValues();
        $texts = [];
        foreach ($schema as $field) {
            if (! $field->type->hasValue() || $values->isEmpty($field)) {
                continue;
            }
            $texts[] = $values->display($field, $this->extensions);
        }

        return $texts;
    }

    /**
     * Exportspalten und -zeile eines Trägers (leer ohne Schema).
     *
     * @param  class-string<Model>|string  $subject
     * @return list<array{key: string, label: string}>
     */
    public function exportColumns(string $subject, int $organizationId): array {
        return $this->export->columns($this->schemaFor($subject, $organizationId));
    }

    /** @return list<string> */
    public function exportRow(Model&CustomFieldSubject $subject): array {
        return $this->export->row($this->schemaFor($subject), $subject->customValues());
    }

    public function usageCount(CustomFieldDefinition $definition): int {
        return CustomFieldValue::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $definition->organization_id)
            ->where('subject_type', $definition->subject_alias)
            ->count();
    }

    /**
     * Eigene Felder, die als Spalte der Übersicht erscheinen (Welle 4.6).
     *
     * @param  class-string  $subjectClass
     * @return list<FieldDefinition>
     */
    public function listColumns(string $subjectClass, int $organizationId): array {
        return array_values(array_filter($this->schemaFor($subjectClass, $organizationId)->all(), static fn (FieldDefinition $field): bool => $field->listed));
    }

    /**
     * Listenspalten und die Werte der angezeigten Datensätze in einem Zug
     * vorladen (kein Nachladen je Zeile).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, covariant Model>  $models
     * @param  class-string  $subjectClass
     * @return list<FieldDefinition>
     */
    public function listColumnsFor(\Illuminate\Database\Eloquent\Collection $models, string $subjectClass, int $organizationId): array {
        $columns = $this->listColumns($subjectClass, $organizationId);
        if ($columns !== []) {
            $models->loadMissing('customFieldValue');
        }

        return $columns;
    }
}
