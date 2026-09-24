<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldSchema.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Enums\Fields\FieldType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Geordnete Felddefinitionen (MVP-866) — die Struktur hinter
 * `form_templates.fields`, `form_submissions.fields_snapshot` und dem
 * Schema-Teil von Checklisten. Liest tolerant ({@see fromArray()}), prüft
 * streng ({@see fromRows()} für Eingaben aus dem Vorlagendialog) und wertet
 * Sichtbarkeitsbedingungen aus.
 *
 * @phpstan-import-type Condition from FieldDefinition
 * @phpstan-import-type DefinitionArray from FieldDefinition
 *
 * @implements \IteratorAggregate<int, FieldDefinition>
 */
final class FieldSchema implements \Countable, \IteratorAggregate {
    public const MAX_FIELDS = 50;

    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    /** Zulässige Operatoren der Sichtbarkeits-Bedingung. */
    public const CONDITION_OPS = ['eq', 'ne', 'in', 'filled'];

    /** @var list<FieldDefinition> */
    private array $fields;

    /** @param  list<FieldDefinition>  $fields */
    public function __construct(array $fields) {
        $this->fields = $fields;
    }

    /** Gespeicherte Definitionen lesen (Altbestand: frühere Typnamen). */
    public static function fromArray(mixed $rows): self {
        $fields = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && trim((string) ($row['key'] ?? '')) !== '') {
                $fields[] = FieldDefinition::fromArray($row);
            }
        }

        return new self($fields);
    }

    /**
     * Rohe Zeilen aus dem Dialog (label/type/required/options als Komma-
     * Liste — oder bereits strukturierte Arrays) normalisieren und prüfen.
     * Fehlende keys entstehen slug-artig aus dem Label; Bedingungen dürfen
     * das Bezugsfeld über sein Label nennen. Wirft eine ValidationException
     * (Key `fields`).
     *
     * @param  array<int|string, mixed>  $rows
     *
     * @throws ValidationException
     */
    public static function fromRows(array $rows): self {
        $fields = [];
        $seen = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                self::fail(__('fields.validation.invalid_row', ['row' => $index + 1]));
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' || mb_strlen($label) > 160) {
                self::fail(__('fields.validation.label_required', ['row' => $index + 1]));
            }
            $type = FieldType::fromStored((string) ($row['type'] ?? ''));
            if ($type === null) {
                self::fail(__('fields.validation.unknown_type', ['row' => $index + 1]));
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($label, '_');
            }
            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                self::fail(__('fields.validation.invalid_key', ['key' => $key !== '' ? $key : $label]));
            }
            if (isset($seen[$key])) {
                self::fail(__('fields.validation.duplicate_key', ['key' => $key]));
            }
            $seen[$key] = true;
            $options = $type->needsOptions() ? self::normalizeOptions($row['options'] ?? []) : [];
            if ($type->needsOptions() && $options === []) {
                self::fail(__('fields.validation.select_needs_options', ['label' => $label]));
            }
            $help = trim((string) ($row['help'] ?? ''));
            $unit = trim((string) ($row['unit'] ?? ''));
            [$min, $max, $step] = $type->supportsRange()
                ? [self::number($row['min'] ?? null), self::number($row['max'] ?? null), self::number($row['step'] ?? null)]
                : [null, null, null];
            if ($min !== null && $max !== null && $min > $max) {
                self::fail(__('fields.validation.range_invalid', ['label' => $label]));
            }
            $fields[] = new FieldDefinition(
                key: $key,
                type: $type,
                label: $label,
                required: filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOL),
                options: $options,
                help: $help === '' ? null : Str::limit($help, 500, ''),
                unit: ($type->supportsUnit() && $unit !== '') ? Str::limit($unit, 20, '') : null,
                visibleIf: self::normalizeCondition($row['visible_if'] ?? null),
                min: $min,
                max: $max,
                step: $step,
                extension: self::extensionKey($row['extension'] ?? null),
                listed: filter_var($row['listed'] ?? false, FILTER_VALIDATE_BOOL),
            );
        }
        if ($fields === []) {
            self::fail(__('fields.validation.fields_required'));
        }
        if (count($fields) > self::MAX_FIELDS) {
            self::fail(__('fields.validation.too_many_fields', ['max' => self::MAX_FIELDS]));
        }
        $schema = new self(self::resolveConditionReferences($fields));
        $schema->assertConditionsResolvable();

        return $schema;
    }

    /** @return list<DefinitionArray> */
    public function toArray(): array {
        return array_map(static fn (FieldDefinition $field): array => $field->toArray(), $this->fields);
    }

    /** @return list<FieldDefinition> */
    public function all(): array {
        return $this->fields;
    }

    public function get(string $key): ?FieldDefinition {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /** @return list<string> */
    public function keys(): array {
        return array_map(static fn (FieldDefinition $field): string => $field->key, $this->fields);
    }

    public function isEmpty(): bool {
        return $this->fields === [];
    }

    public function count(): int {
        return count($this->fields);
    }

    /** @return \ArrayIterator<int, FieldDefinition> */
    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->fields);
    }

    /** Felder ohne Upload-/Signaturtyp (z. B. Kundenportal ohne Dateikanal). */
    public function withoutAttachments(): self {
        return new self(array_values(array_filter($this->fields, static fn (FieldDefinition $field): bool => ! $field->type->storesAttachment())));
    }

    /**
     * Aktuell sichtbare Felder gegen eingegebene Werte — maßgeblich für die
     * Pflichtprüfung (unsichtbare Pflichtfelder werden übersprungen) und die
     * Anzeige. Felder ohne Bedingung sind immer sichtbar.
     *
     * @param  array<string, mixed>  $values
     */
    public function visibleFor(array $values): self {
        return new self(array_values(array_filter($this->fields, static fn (FieldDefinition $field): bool => self::isVisible($field, $values))));
    }

    /** @param  array<string, mixed>  $values */
    public static function isVisible(FieldDefinition $field, array $values): bool {
        $condition = $field->visibleIf;
        if ($condition === null) {
            return true;
        }
        $actual = self::scalarize($values[$condition['field']] ?? null);
        $expected = $condition['value'];

        return match ($condition['op']) {
            'filled' => $actual !== '' && $actual !== '0',
            'ne' => $actual !== $expected,
            'in' => in_array($actual, self::splitList($expected), true),
            default => $actual === $expected,
        };
    }

    /** @return array<string, string> Anzeigenamen je Eingabefeld für Fehlermeldungen. */
    public function attributeNames(string $prefix = 'values'): array {
        $names = [];
        foreach ($this->fields as $field) {
            $names[$prefix === '' ? $field->key : $prefix . '.' . $field->key] = $field->label;
        }

        return $names;
    }

    /** @throws ValidationException */
    private function assertConditionsResolvable(): void {
        $deps = [];
        $keys = array_fill_keys($this->keys(), true);
        foreach ($this->fields as $field) {
            $ref = $field->visibleIf['field'] ?? null;
            if ($ref === null) {
                continue;
            }
            if (! isset($keys[$ref])) {
                self::fail(__('fields.validation.condition_unknown_field', ['field' => $ref, 'label' => $field->key]));
            }
            $deps[$field->key] = $ref;
        }
        // Kantenverfolgung key → Bezugsfeld: taucht ein Key auf dem eigenen
        // Pfad erneut auf, liegt ein Zyklus vor.
        foreach (array_keys($deps) as $start) {
            $seen = [];
            $node = $start;
            while (isset($deps[$node])) {
                if (isset($seen[$node])) {
                    self::fail(__('fields.validation.condition_cycle', ['field' => $start]));
                }
                $seen[$node] = true;
                $node = $deps[$node];
            }
        }
    }

    /**
     * Bedingungen aus dem Dialog nennen das Bezugsfeld per Label — auf den
     * Key umschreiben (erste Verwendung eines Labels gewinnt).
     *
     * @param  list<FieldDefinition>  $fields
     * @return list<FieldDefinition>
     */
    private static function resolveConditionReferences(array $fields): array {
        $keys = [];
        $labelToKey = [];
        foreach ($fields as $field) {
            $keys[$field->key] = true;
            $labelToKey[$field->label] ??= $field->key;
        }
        foreach ($fields as $i => $field) {
            $ref = $field->visibleIf['field'] ?? null;
            if ($field->visibleIf === null || $ref === null || isset($keys[$ref]) || ! isset($labelToKey[$ref])) {
                continue;
            }
            $condition = ['field' => $labelToKey[$ref], 'op' => $field->visibleIf['op'], 'value' => $field->visibleIf['value']];
            $fields[$i] = new FieldDefinition($field->key, $field->type, $field->label, $field->required, $field->options, $field->help, $field->unit, $condition, $field->min, $field->max, $field->step, $field->extension);
        }

        return $fields;
    }

    /** @return Condition|null */
    private static function normalizeCondition(mixed $raw): ?array {
        if (! is_array($raw)) {
            return null;
        }
        $field = trim((string) ($raw['field'] ?? ''));
        if ($field === '') {
            return null;
        }
        $op = (string) ($raw['op'] ?? 'eq');
        if (! in_array($op, self::CONDITION_OPS, true)) {
            $op = 'eq';
        }
        // `filled` prüft nur die Belegung → Wert verwerfen.
        $value = $op === 'filled' ? '' : Str::limit(trim((string) ($raw['value'] ?? '')), 500, '');

        return ['field' => $field, 'op' => $op, 'value' => $value];
    }

    /** @return list<string> */
    private static function normalizeOptions(mixed $options): array {
        if (is_string($options)) {
            $options = explode(',', $options);
        }
        if (! is_array($options)) {
            return [];
        }
        $clean = [];
        foreach ($options as $option) {
            $option = trim((string) $option);
            if ($option !== '' && mb_strlen($option) <= 120 && ! in_array($option, $clean, true)) {
                $clean[] = $option;
            }
        }

        return $clean;
    }

    private static function extensionKey(mixed $raw): ?string {
        $key = is_scalar($raw) ? trim((string) $raw) : '';

        return $key === '' ? null : Str::limit($key, 60, '');
    }

    private static function number(mixed $raw): int|float|null {
        if (is_int($raw) || is_float($raw)) {
            return $raw;
        }
        if (! is_string($raw) || ! is_numeric(trim($raw))) {
            return null;
        }
        $raw = trim($raw);

        return str_contains($raw, '.') ? (float) $raw : (int) $raw;
    }

    private static function scalarize(mixed $value): string {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return implode(',', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value));
        }

        return $value === null || ! is_scalar($value) ? '' : trim((string) $value);
    }

    /** @return list<string> */
    private static function splitList(string $value): array {
        $items = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function fail(string $message): never {
        throw ValidationException::withMessages(['fields' => $message]);
    }
}
