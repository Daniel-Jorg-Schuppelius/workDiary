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

    /**
     * Normalisiert rohe Feld-Zeilen (aus dem Dialog: label/type/required/
     * options als Komma-Liste — oder bereits strukturierte Arrays) in die
     * kanonische Definition. Fehlende keys werden slug-artig aus dem Label
     * abgeleitet. Wirft eine ValidationException (Key `fields`) bei
     * Strukturfehlern.
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null}>
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
            ];
        }

        if ($fields === []) {
            self::fail(__('form.validation.fields_required'));
        }
        if (count($fields) > self::MAX_FIELDS) {
            self::fail(__('form.validation.too_many_fields', ['max' => self::MAX_FIELDS]));
        }

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

            $typeRules = match ($type) {
                FormFieldType::Text => ['string', 'max:500'],
                FormFieldType::Textarea => ['string', 'max:10000'],
                FormFieldType::Number => ['numeric'],
                FormFieldType::Date => ['date'],
                FormFieldType::Select => ['string', Rule::in((array) ($field['options'] ?? []))],
            };

            $rules['values.' . $field['key']] = [$required ? 'required' : 'nullable', ...$typeRules];
        }

        return $rules;
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
            $number = rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

            return $unit === '' ? $number : $number . ' ' . $unit;
        }

        return (string) $value;
    }

    /** @throws ValidationException */
    private static function fail(string $message): never {
        throw ValidationException::withMessages(['fields' => $message]);
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
