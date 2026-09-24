<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Enums\Fields\FieldType;

/**
 * Eine Felddefinition des Feldschema-Bausteins (MVP-866): Schlüssel, Typ,
 * Bezeichnung, Pflicht, Optionen, Grenzen, Einheit, Hilfe, Sichtbarkeits-
 * bedingung und optional ein Fachtyp (`extension`), den ein Modul über
 * {@see Contracts\FieldExtension} registriert.
 *
 * @phpstan-type Condition array{field: string, op: string, value: string}
 * @phpstan-type DefinitionArray array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null, visible_if: Condition|null, min?: int|float, max?: int|float, step?: int|float, extension?: string, option_labels?: array<string, string>, listed?: true}
 */
final readonly class FieldDefinition {
    /**
     * @param  list<string>  $options
     * @param  Condition|null  $visibleIf
     * @param  array<string, string>|null  $optionLabels  Anzeigetext je Option, wenn er vom Wert abweicht (Protokoll: key → label)
     * @param  bool  $listed  eigenes Feld als Spalte der Übersicht zeigen (Welle 4.6)
     */
    public function __construct(
        public string $key,
        public FieldType $type,
        public string $label,
        public bool $required = false,
        public array $options = [],
        public ?string $help = null,
        public ?string $unit = null,
        public ?array $visibleIf = null,
        public int|float|null $min = null,
        public int|float|null $max = null,
        public int|float|null $step = null,
        public ?string $extension = null,
        public ?array $optionLabels = null,
        public bool $listed = false,
    ) {}

    /**
     * Gespeicherte Definition lesen — tolerant gegenüber Altbestand (frühere
     * Typnamen, fehlende Schlüssel). Unbekannter Typ wird zu Text.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self {
        $condition = $row['visible_if'] ?? null;
        $visibleIf = is_array($condition) && is_string($condition['field'] ?? null) && $condition['field'] !== ''
            ? ['field' => $condition['field'], 'op' => (string) ($condition['op'] ?? 'eq'), 'value' => (string) ($condition['value'] ?? '')]
            : null;
        $options = [];
        foreach ((array) ($row['options'] ?? []) as $option) {
            if (is_scalar($option) && trim((string) $option) !== '') {
                $options[] = (string) $option;
            }
        }

        return new self(
            key: (string) ($row['key'] ?? ''),
            type: FieldType::fromStored((string) ($row['type'] ?? '')) ?? FieldType::Text,
            label: (string) ($row['label'] ?? ($row['key'] ?? '')),
            required: filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOL),
            options: $options,
            help: self::stringOrNull($row['help'] ?? null),
            unit: self::stringOrNull($row['unit'] ?? null),
            visibleIf: $visibleIf,
            min: self::numberOrNull($row['min'] ?? null),
            max: self::numberOrNull($row['max'] ?? null),
            step: self::numberOrNull($row['step'] ?? null),
            extension: self::stringOrNull($row['extension'] ?? null),
            optionLabels: self::labels($row['option_labels'] ?? null),
            listed: filter_var($row['listed'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }

    /** Anzeigetext einer Option (Wert selbst, wenn kein abweichender Text hinterlegt ist). */
    public function optionLabel(string $option): string {
        return $this->optionLabels[$option] ?? $option;
    }

    /**
     * Kanonische Speicherform. Die Grundschlüssel stehen immer (Reihenfolge
     * wie bisher in `form_templates.fields`), Grenzen und Fachtyp nur wenn
     * gesetzt — Altbestand bleibt byte-gleich.
     *
     * @return DefinitionArray
     */
    public function toArray(): array {
        $row = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'options' => $this->options,
            'help' => $this->help,
            'unit' => $this->unit,
            'visible_if' => $this->visibleIf,
        ];
        foreach (['min' => $this->min, 'max' => $this->max, 'step' => $this->step, 'extension' => $this->extension, 'option_labels' => $this->optionLabels] as $name => $value) {
            if ($value !== null) {
                $row[$name] = $value;
            }
        }
        // Nur gesetzt schreiben, damit bestehende Schemata byte-gleich bleiben.
        if ($this->listed) {
            $row['listed'] = true;
        }

        return $row;
    }

    public function hasCondition(): bool {
        return $this->visibleIf !== null;
    }

    private static function stringOrNull(mixed $value): ?string {
        if (! is_scalar($value)) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /** @return array<string, string>|null */
    private static function labels(mixed $raw): ?array {
        if (! is_array($raw) || $raw === []) {
            return null;
        }
        $labels = [];
        foreach ($raw as $key => $label) {
            if (is_scalar($label)) {
                $labels[(string) $key] = (string) $label;
            }
        }

        return $labels === [] ? null : $labels;
    }

    private static function numberOrNull(mixed $value): int|float|null {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return null;
    }
}
