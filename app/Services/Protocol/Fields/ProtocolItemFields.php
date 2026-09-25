<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Fields;

use App\Enums\Protocol\ProtocolItemType;
use App\Models\Protocol\{Protocol, ProtocolItem};
use App\Services\Fields\{FieldDefinition, FieldSchema, FieldValues};

/**
 * Adapter zwischen Protokollpunkt und Feldschema-Baustein (MVP-867). Der
 * Punkt speichert Konfiguration und Wert gemeinsam in `value_json` — diese
 * Form ist hash-relevant ({@see \App\Services\Protocol\ProtocolHasher}) und
 * bleibt unverändert: Definition (Typ, Optionen, Grenzen, Einheit, Fachtyp)
 * und Wert (`text`, `value`, `selected`, `attachment_ids`, `signature_id`,
 * `samples`, Mangel) werden gelesen, `fromInput()` bildet die Formulareingabe
 * zurück auf die Wertschlüssel (MVP-883).
 */
class ProtocolItemFields {
    public function definition(ProtocolItem $item): FieldDefinition {
        $type = $item->item_type;
        $value = is_array($item->value_json) ? $item->value_json : [];
        $options = [];
        $labels = [];
        foreach ((array) ($value['options'] ?? []) as $option) {
            if (is_array($option) && isset($option['key']) && is_scalar($option['key'])) {
                $key = (string) $option['key'];
                $options[] = $key;
                $labels[$key] = is_scalar($option['label'] ?? null) ? (string) $option['label'] : $key;
            }
        }
        [$minKey, $maxKey] = match ($type) {
            ProtocolItemType::Text => ['min_length', 'max_length'],
            ProtocolItemType::Photo, ProtocolItemType::File => ['min_count', 'max_count'],
            default => ['min', 'max'],
        };

        // Pflicht prüft der Validator vor der Regelprüfung selbst: ein
        // Ja/Nein-Punkt mit „nein" ist ausgefüllt, kein Regelverstoß.
        return new FieldDefinition(
            key: self::key($item),
            type: $type->fieldType(),
            label: $item->label,
            required: false,
            options: $options,
            unit: is_scalar($value['unit'] ?? null) && (string) $value['unit'] !== '' ? (string) $value['unit'] : null,
            min: self::number($value[$minKey] ?? null),
            max: self::number($value[$maxKey] ?? null),
            extension: $type->fieldExtension(),
            optionLabels: $labels === [] ? null : $labels,
        );
    }

    /** Erfasster Wert des Punkts in der Form, die der Grundtyp bzw. Fachtyp erwartet. */
    public function value(ProtocolItem $item): mixed {
        $value = is_array($item->value_json) ? $item->value_json : [];

        return match ($item->item_type) {
            ProtocolItemType::Text => $value['text'] ?? null,
            ProtocolItemType::Boolean, ProtocolItemType::Number, ProtocolItemType::Range,
            ProtocolItemType::Date, ProtocolItemType::DateTime => $value['value'] ?? null,
            ProtocolItemType::Choice, ProtocolItemType::Multichoice => $value['selected'] ?? null,
            ProtocolItemType::Photo, ProtocolItemType::File => $value['attachment_ids'] ?? null,
            ProtocolItemType::Signature => $value['signature_id'] ?? null,
            ProtocolItemType::MeasurementTimestamped => $value['samples'] ?? null,
            ProtocolItemType::Defect => $value === [] ? null : $value,
            ProtocolItemType::Group, ProtocolItemType::ProcedureStep, ProtocolItemType::SignoffInternal => null,
        };
    }

    public function values(ProtocolItem $item): FieldValues {
        return new FieldValues([self::key($item) => $this->value($item)]);
    }

    public function schema(Protocol $protocol): FieldSchema {
        $fields = [];
        foreach ($protocol->items->sortBy('sort_order') as $item) {
            $fields[] = $this->definition($item);
        }

        return new FieldSchema($fields);
    }

    /**
     * Formularwert aus `x-field-input` → Wertschlüssel des `value_json`.
     * Konfiguration (Optionen, Grenzen, Einheit) bleibt unberührt; der Dienst
     * legt das Ergebnis darüber. Null: Typ wird nicht über das Formular
     * gefüllt (Fotos über den Fotostreifen, Unterschrift über die Signatur).
     *
     * @return array<string, mixed>|null
     */
    public function fromInput(ProtocolItem $item, mixed $input): ?array {
        $current = is_array($item->value_json) ? $item->value_json : [];

        return match ($item->item_type) {
            ProtocolItemType::Text => ['text' => is_scalar($input) ? trim((string) $input) : ''],
            ProtocolItemType::Boolean => ['value' => filter_var($input, FILTER_VALIDATE_BOOL)],
            ProtocolItemType::Number, ProtocolItemType::Range => ['value' => self::number($input)],
            ProtocolItemType::Date, ProtocolItemType::DateTime => ['value' => is_scalar($input) && (string) $input !== '' ? (string) $input : null],
            ProtocolItemType::Choice => ['selected' => is_scalar($input) && (string) $input !== '' ? (string) $input : null],
            ProtocolItemType::Multichoice => ['selected' => array_values(array_map('strval', array_filter(is_array($input) ? $input : [], 'is_scalar')))],
            ProtocolItemType::Defect => is_array($input) ? [
                'severity' => is_scalar($input['severity'] ?? null) ? (string) $input['severity'] : null,
                'description' => is_scalar($input['description'] ?? null) ? trim((string) $input['description']) : '',
                'category' => is_scalar($input['category'] ?? null) && (string) $input['category'] !== '' ? (string) $input['category'] : null,
            ] : null,
            // Messreihe: jede Eingabe ist ein weiterer Messpunkt mit Zeitstempel.
            ProtocolItemType::MeasurementTimestamped => self::number($input) === null ? null : ['samples' => [
                ...array_values((array) ($current['samples'] ?? [])),
                ['value' => self::number($input), 'at' => now()->toIso8601String()],
            ]],
            default => null,
        };
    }

    public static function key(ProtocolItem $item): string {
        return 'item_' . (int) $item->id;
    }

    private static function number(mixed $raw): int|float|null {
        if (is_int($raw) || is_float($raw)) {
            return $raw;
        }

        return is_string($raw) && is_numeric($raw) ? (str_contains($raw, '.') ? (float) $raw : (int) $raw) : null;
    }
}
