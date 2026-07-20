<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormFieldDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Form;

use App\Enums\Form\FormFieldType;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Validation\{Rule, ValidationException};

/**
 * Felddefinitionen des Formularsystems (Feature 032).
 *
 * Eine Vorlage trägt ihre Felder als JSON-Array von
 * {key, label, type, required, options[], help, unit}. Diese Klasse
 * (a) normalisiert/validiert die Struktur beim Speichern der Vorlage
 * (keys eindeutig + slug-artig, type bekannt, select braucht options)
 * und (b) erzeugt die Laravel-Validierungsregeln für das Ausfüllen.
 */
final class FormFieldDefinition {
    public const MAX_FIELDS = 50;

    /** Zulässige Operatoren der Sichtbarkeits-Bedingung (Rang 33). */
    public const CONDITION_OPS = ['eq', 'ne', 'in', 'filled'];

    /**
     * Normalisiert rohe Feld-Zeilen (aus dem Dialog: label/type/required/
     * options als Komma-Liste — oder bereits strukturierte Arrays) in die
     * kanonische Definition. Fehlende keys werden slug-artig aus dem Label
     * abgeleitet. Wirft eine ValidationException (Key `fields`) bei
     * Strukturfehlern.
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null, visible_if: array{field: string, op: string, value: string}|null}>
     *
     * @throws ValidationException
     */
    public static function normalize(array $rows): array {
        $fields = [];
        $seenKeys = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                self::fail(__('form.validation.invalid_row', ['row' => $index + 1]));
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' || mb_strlen($label) > 160) {
                self::fail(__('form.validation.label_required', ['row' => $index + 1]));
            }

            $type = FormFieldType::tryFrom((string) ($row['type'] ?? ''));
            if ($type === null) {
                self::fail(__('form.validation.unknown_type', ['row' => $index + 1]));
            }

            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($label, '_');
            }
            if (preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key) !== 1) {
                self::fail(__('form.validation.invalid_key', ['key' => $key !== '' ? $key : $label]));
            }
            if (isset($seenKeys[$key])) {
                self::fail(__('form.validation.duplicate_key', ['key' => $key]));
            }
            $seenKeys[$key] = true;

            $options = self::normalizeOptions($row['options'] ?? []);
            if ($type->needsOptions() && $options === []) {
                self::fail(__('form.validation.select_needs_options', ['label' => $label]));
            }
            if (! $type->needsOptions()) {
                $options = [];
            }

            $help = trim((string) ($row['help'] ?? ''));
            $unit = trim((string) ($row['unit'] ?? ''));

            $fields[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type->value,
                'required' => filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOL),
                'options' => $options,
                'help' => $help === '' ? null : Str::limit($help, 500, ''),
                'unit' => ($type->supportsUnit() && $unit !== '') ? Str::limit($unit, 20, '') : null,
                'visible_if' => self::normalizeVisibleIf($row['visible_if'] ?? null),
            ];
        }

        if ($fields === []) {
            self::fail(__('form.validation.fields_required'));
        }
        if (count($fields) > self::MAX_FIELDS) {
            self::fail(__('form.validation.too_many_fields', ['max' => self::MAX_FIELDS]));
        }

        $fields = self::resolveConditionReferences($fields);
        self::assertConditionsResolvable($fields);

        return $fields;
    }

    /**
     * Laravel-Validierungsregeln für das Ausfüllen (Input-Schema
     * `values[<key>]`), abgeleitet aus einer normalisierten Definition.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, list<mixed>>
     */
    public static function rules(array $fields): array {
        $rules = [];

        foreach ($fields as $field) {
            $type = FormFieldType::from((string) $field['type']);
            $required = (bool) ($field['required'] ?? false);

            // Pflicht-Checkbox heißt fachlich „muss angehakt sein" → accepted.
            if ($type === FormFieldType::Checkbox) {
                $rules['values.' . $field['key']] = $required
                    ? ['accepted']
                    : ['nullable', 'boolean'];

                continue;
            }

            // Foto/Datei/Unterschrift (Rang 32) tragen keinen Skalar in `values`
            // — ihr Inhalt kommt über den Datei-/Signatur-Kanal (Attachment);
            // Pflicht wird separat in FormService::submit geprüft.
            $typeRules = match ($type) {
                FormFieldType::Text => ['string', 'max:500'],
                FormFieldType::Textarea => ['string', 'max:10000'],
                FormFieldType::Number => ['numeric'],
                FormFieldType::Date => ['date'],
                FormFieldType::Select => ['string', Rule::in((array) ($field['options'] ?? []))],
                FormFieldType::Photo, FormFieldType::File, FormFieldType::Signature => [],
            };

            $rules['values.' . $field['key']] = [
                ($required && ! $type->storesAttachment()) ? 'required' : 'nullable',
                ...$typeRules,
            ];
        }

        return $rules;
    }

    /**
     * Filtert die aktuell sichtbaren Felder anhand ihrer `visible_if`-Bedingung
     * (Rang 33) gegen die eingegebenen Werte. Felder ohne Bedingung sind immer
     * sichtbar. Maßgeblich für die Pflichtprüfung (unsichtbare Pflichtfelder
     * werden serverseitig übersprungen) und für die Anzeige in Show/PDF.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    public static function visibleFields(array $fields, array $values): array {
        return array_values(array_filter(
            $fields,
            static fn(array $field): bool => self::isVisible($field, $values),
        ));
    }

    /**
     * Wertet die `visible_if`-Bedingung eines Feldes gegen die Werte aus.
     * Ohne (gültige) Bedingung ist das Feld sichtbar.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $values
     */
    public static function isVisible(array $field, array $values): bool {
        $condition = $field['visible_if'] ?? null;
        if (! is_array($condition) || ! is_string($condition['field'] ?? null) || $condition['field'] === '') {
            return true;
        }

        $actual = self::scalarize($values[$condition['field']] ?? null);
        $op = is_string($condition['op'] ?? null) ? $condition['op'] : 'eq';
        $expected = (string) ($condition['value'] ?? '');

        return match ($op) {
            'filled' => $actual !== '' && $actual !== '0',
            'ne' => $actual !== $expected,
            'in' => in_array($actual, self::splitList($expected), true),
            default => $actual === $expected, // eq
        };
    }

    /**
     * Anzeigenamen je Feld-Key (für sprechende Validierungs-Fehlermeldungen).
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, string>
     */
    public static function attributeNames(array $fields): array {
        $names = [];
        foreach ($fields as $field) {
            $names['values.' . $field['key']] = (string) $field['label'];
        }

        return $names;
    }

    /**
     * Bringt validierte Eingaben in typtreue Speicherwerte (number → float,
     * checkbox → bool, Rest → string|null). Unbekannte Keys werden verworfen.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, bool|float|string|null>
     */
    public static function normalizeValues(array $fields, array $values): array {
        $normalized = [];

        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $type = FormFieldType::from((string) $field['type']);
            $raw = $values[$key] ?? null;

            if ($type === FormFieldType::Checkbox) {
                $normalized[$key] = filter_var($raw ?? false, FILTER_VALIDATE_BOOL);

                continue;
            }

            if ($raw === null || $raw === '') {
                $normalized[$key] = null;

                continue;
            }

            $normalized[$key] = $type === FormFieldType::Number
                ? (float) $raw
                : (string) $raw;
        }

        return $normalized;
    }

    /**
     * Anzeige-/Druckwert eines Feldes (Checkbox → Ja/Nein, Datum formatiert,
     * Zahl mit Einheit).
     *
     * @param  array<string, mixed>  $field
     */
    public static function displayValue(array $field, mixed $value): string {
        $type = FormFieldType::tryFrom((string) ($field['type'] ?? ''));

        if ($type === FormFieldType::Checkbox) {
            return (bool) $value
                ? (string) __('form.value.yes')
                : (string) __('form.value.no');
        }

        // Unterschrift: der Wert ist nur ein Marker; das Bild wird in Show/PDF
        // über das Attachment (meta_type field:<key>) eingebettet.
        if ($type === FormFieldType::Signature) {
            return $value ? (string) __('form.value.signed') : '—';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        if ($type === FormFieldType::Date) {
            try {
                return \App\Support\CarbonFmt::fdate(Carbon::parse((string) $value));
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if ($type === FormFieldType::Number) {
            $unit = trim((string) ($field['unit'] ?? ''));
            $number = rtrim(rtrim(NumberHelper::toGermanFormat((float) $value, 2, withThousandsSeparator: true), '0'), ',');

            return $unit === '' ? $number : $number . ' ' . $unit;
        }

        return (string) $value;
    }

    /** @throws ValidationException */
    private static function fail(string $message): never {
        throw ValidationException::withMessages(['fields' => $message]);
    }

    /**
     * Normalisiert eine rohe `visible_if`-Zeile in {field, op, value} oder null
     * (keine Bedingung). Unbekannte Operatoren fallen auf `eq` zurück; ein
     * leeres Bezugsfeld hebt die Bedingung auf.
     *
     * @return array{field: string, op: string, value: string}|null
     */
    private static function normalizeVisibleIf(mixed $raw): ?array {
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

        // `filled` ist wertlos (prüft nur Belegung) → Wert verwerfen.
        $value = $op === 'filled' ? '' : Str::limit(trim((string) ($raw['value'] ?? '')), 500, '');

        return ['field' => $field, 'op' => $op, 'value' => $value];
    }

    /**
     * Löst Bedingungs-Referenzen zum kanonischen Feld-Key auf: Der Editor
     * referenziert das Bezugsfeld über sein Label (Keys entstehen erst hier);
     * bereits kanonische Keys (z. B. aus Snapshots/API) bleiben unverändert.
     *
     * @param  list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null, visible_if: array{field: string, op: string, value: string}|null}>  $fields
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null, visible_if: array{field: string, op: string, value: string}|null}>
     */
    private static function resolveConditionReferences(array $fields): array {
        $keys = [];
        $labelToKey = [];
        foreach ($fields as $field) {
            $keys[$field['key']] = true;
            // Erste Verwendung eines Labels gewinnt (Keys sind ohnehin eindeutig).
            $labelToKey[$field['label']] ??= $field['key'];
        }

        foreach ($fields as $i => $field) {
            $ref = $field['visible_if']['field'] ?? null;
            if ($ref === null || isset($keys[$ref])) {
                continue;
            }
            if (isset($labelToKey[$ref])) {
                $fields[$i]['visible_if']['field'] = $labelToKey[$ref];
            }
        }

        return $fields;
    }

    /**
     * Stellt sicher, dass jede `visible_if`-Bedingung ein existierendes anderes
     * Feld referenziert und der Bedingungsgraph zyklenfrei ist (sonst könnten
     * sich Felder gegenseitig aus-/einblenden). Selbstbezug ist ein Zyklus.
     *
     * @param  list<array{key: string, visible_if: array{field: string, op: string, value: string}|null}>  $fields
     *
     * @throws ValidationException
     */
    private static function assertConditionsResolvable(array $fields): void {
        $deps = [];
        $keys = [];
        foreach ($fields as $field) {
            $keys[$field['key']] = true;
        }
        foreach ($fields as $field) {
            $ref = $field['visible_if']['field'] ?? null;
            if ($ref === null) {
                continue;
            }
            if (! isset($keys[$ref])) {
                self::fail(__('form.validation.condition_unknown_field', ['field' => $ref, 'label' => $field['key']]));
            }
            $deps[$field['key']] = $ref;
        }

        // Kantenverfolgung key → referenziertes Feld: taucht ein Key auf dem
        // eigenen Pfad erneut auf, liegt ein Zyklus vor.
        foreach (array_keys($deps) as $start) {
            $seen = [];
            $node = $start;
            while (isset($deps[$node])) {
                if (isset($seen[$node])) {
                    self::fail(__('form.validation.condition_cycle', ['field' => $start]));
                }
                $seen[$node] = true;
                $node = $deps[$node];
            }
        }
    }

    /** Bringt einen Feldwert für den Bedingungsvergleich in eine Zeichenkette. */
    private static function scalarize(mixed $value): string {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Zerlegt eine Komma-Liste (Operator `in`) in getrimmte Einzelwerte.
     *
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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
}
