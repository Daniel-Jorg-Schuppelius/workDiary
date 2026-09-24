<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldValidator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Enums\Fields\FieldType;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Laravel-Regeln je Felddefinition (MVP-866) — eine Herleitung für
 * Formulare, Checklisten und Portal. Upload- und Signaturfelder tragen
 * keinen Skalar im Eingabe-Array; ihre Pflicht prüft
 * {@see missingAttachments()} gegen den Datei-/Signaturkanal.
 */
class FieldValidator {
    public function __construct(private readonly FieldExtensionRegistry $extensions) {}

    /**
     * @return array<string, list<mixed>> Regeln für `<prefix>.<key>` (Mehrfachauswahl zusätzlich `<prefix>.<key>.*`)
     */
    public function rules(FieldSchema $schema, string $prefix = 'values'): array {
        $rules = [];
        foreach ($schema as $field) {
            if (! $field->type->hasValue()) {
                continue;
            }
            $name = self::name($prefix, $field->key);
            $extension = $this->extensions->get($field->extension);
            if ($extension !== null) {
                $rules[$name] = [$field->required ? 'required' : 'nullable', ...$extension->rules($field)];

                continue;
            }
            // Pflicht-Checkbox heißt fachlich „muss angehakt sein" → accepted.
            if ($field->type === FieldType::Boolean) {
                $rules[$name] = $field->required ? ['accepted'] : ['nullable', 'boolean'];

                continue;
            }
            if ($field->type->storesAttachment()) {
                $rules[$name] = ['nullable', 'string', 'max:500'];

                continue;
            }
            $typeRules = $this->typeRules($field);
            if ($field->type->isMultiValue()) {
                $rules[$name] = [$field->required ? 'required' : 'nullable', 'array', ...($field->required ? ['min:1'] : [])];
                $rules[$name . '.*'] = $typeRules;

                continue;
            }
            $rules[$name] = [$field->required ? 'required' : 'nullable', ...$typeRules];
        }

        return $rules;
    }

    /** Eingabename: `values.key`, ohne Präfix nur `key` (Umfrage `q12`, Prozedur `value`). */
    public static function name(string $prefix, string $key): string {
        return $prefix === '' ? $key : $prefix . '.' . $key;
    }

    /**
     * Fehlende Pflichtinhalte der Upload-/Signaturfelder (Schlüssel
     * `<prefix>.<key>` → Meldung).
     *
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $signatures
     * @param  list<string>  $deferredKeys  Offline nachgereichte Dateien
     * @return array<string, string>
     */
    public function missingAttachments(FieldSchema $schema, array $files, array $signatures, array $deferredKeys = [], string $prefix = 'values'): array {
        $errors = [];
        foreach ($schema as $field) {
            if (! $field->required || ! $field->type->storesAttachment()) {
                continue;
            }
            $present = $field->type->isSignature()
                ? trim((string) ($signatures[$field->key] ?? '')) !== ''
                : (($files[$field->key] ?? null) instanceof UploadedFile || in_array($field->key, $deferredKeys, true));
            if (! $present) {
                $errors[self::name($prefix, $field->key)] = (string) __('validation.required', ['attribute' => $field->label]);
            }
        }

        return $errors;
    }

    /** @return list<mixed> */
    private function typeRules(FieldDefinition $field): array {
        $bounds = [];
        if ($field->min !== null) {
            $bounds[] = 'min:' . $field->min;
        }
        if ($field->max !== null) {
            $bounds[] = 'max:' . $field->max;
        }
        // Datum ohne Relativausdrücke („tomorrow"), aber mit ISO-Offset — wie
        // DateHelper::isDateTime; Laravels `date` ließe strtotime alles durch.
        $strictDate = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_scalar($value) || ! DateHelper::isDateTime((string) $value)) {
                $fail((string) __('validation.date', ['attribute' => $attribute]));
            }
        };

        return match ($field->type) {
            FieldType::Text => ['string', ...($field->min !== null ? ['min:' . $field->min] : []), 'max:' . ($field->max ?? 500)],
            FieldType::Textarea => ['string', ...($field->min !== null ? ['min:' . $field->min] : []), 'max:' . ($field->max ?? 10000)],
            FieldType::Number, FieldType::Measurement => ['numeric', ...$bounds],
            FieldType::Scale => ['integer', 'min:' . ($field->min ?? 1), 'max:' . ($field->max ?? 5)],
            FieldType::Date, FieldType::DateTime => [$strictDate],
            FieldType::Choice, FieldType::Multichoice => ['string', ...($field->options === [] ? [] : [Rule::in($field->options)])],
            FieldType::Boolean, FieldType::Photo, FieldType::File, FieldType::Signature, FieldType::Section => [],
        };
    }
}
