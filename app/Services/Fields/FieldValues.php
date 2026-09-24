<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldValues.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Enums\Fields\FieldType;
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;

/**
 * Werte zu einem Feldschema (MVP-866): typtreu gespeichert (Zahl → float,
 * Skala → int, Checkbox → bool, Mehrfachauswahl → Liste, Rest → string|null),
 * Upload-/Signaturfelder tragen nur einen Anzeige-Marker (Dateiname,
 * „signed"); der Inhalt liegt als Attachment am Träger.
 */
final class FieldValues {
    /** @param  array<string, mixed>  $values */
    public function __construct(private readonly array $values = []) {}

    public static function fromArray(mixed $values): self {
        return new self(is_array($values) ? $values : []);
    }

    /**
     * Validierte Eingaben in Speicherwerte bringen; unbekannte Keys werden
     * verworfen, Abschnitte tragen keinen Wert.
     *
     * @param  array<string, mixed>  $input
     */
    public static function normalize(FieldSchema $schema, array $input, ?FieldExtensionRegistry $extensions = null): self {
        $normalized = [];
        foreach ($schema as $field) {
            if (! $field->type->hasValue()) {
                continue;
            }
            $raw = $input[$field->key] ?? null;
            $extension = $extensions?->get($field->extension);
            if ($extension !== null) {
                $normalized[$field->key] = $extension->normalize($field, $raw);

                continue;
            }
            if ($field->type === FieldType::Boolean) {
                $normalized[$field->key] = filter_var($raw ?? false, FILTER_VALIDATE_BOOL);

                continue;
            }
            if ($raw === null || $raw === '' || $raw === []) {
                $normalized[$field->key] = null;

                continue;
            }
            $normalized[$field->key] = match ($field->type) {
                FieldType::Number, FieldType::Measurement => is_numeric($raw) ? (float) $raw : null,
                FieldType::Scale => is_numeric($raw) ? (int) $raw : null,
                FieldType::Multichoice => array_values(array_map(static fn (mixed $v): string => (string) $v, array_filter((array) $raw, 'is_scalar'))),
                default => is_scalar($raw) ? (string) $raw : null,
            };
        }

        return new self($normalized);
    }

    public function get(string $key): mixed {
        return $this->values[$key] ?? null;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->values);
    }

    public function isEmpty(FieldDefinition $field): bool {
        $value = $this->get($field->key);

        return $value === null || $value === '' || $value === [] || ($field->type === FieldType::Boolean && $value === false);
    }

    /** @param  array<string, mixed>  $values  Anzeige-Marker der Anhänge o. Ä. */
    public function with(array $values): self {
        return new self(array_merge($this->values, $values));
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return $this->values;
    }

    /** Anzeige-/Druckwert (Checkbox → Ja/Nein, Datum formatiert, Zahl mit Einheit, Skala „3 / 5"). */
    public function display(FieldDefinition $field, ?FieldExtensionRegistry $extensions = null): string {
        $value = $this->get($field->key);
        $extension = $extensions?->get($field->extension);
        if ($extension !== null) {
            return $extension->display($field, $value);
        }
        if ($field->type === FieldType::Boolean) {
            return (string) __((bool) $value ? 'fields.value.yes' : 'fields.value.no');
        }
        // Unterschrift: nur Marker; das Bild kommt über das Attachment.
        if ($field->type === FieldType::Signature) {
            return $value ? (string) __('fields.value.signed') : '—';
        }
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }
        if (($field->type === FieldType::Date || $field->type === FieldType::DateTime) && is_scalar($value)) {
            try {
                $date = Carbon::parse((string) $value);

                return $field->type === FieldType::Date ? CarbonFmt::fdate($date) : CarbonFmt::fdatetime($date);
            } catch (\Throwable) {
                return (string) $value;
            }
        }
        if (($field->type === FieldType::Number || $field->type === FieldType::Measurement) && is_numeric($value)) {
            $number = NumberHelper::toGermanFormat((float) $value, 2, withThousandsSeparator: true, trimTrailingZeros: true);

            return $field->unit === null ? $number : $number . ' ' . $field->unit;
        }
        if ($field->type === FieldType::Scale && is_numeric($value)) {
            return (string) (int) $value . ' / ' . (string) ($field->max ?? 5);
        }
        if (is_array($value)) {
            return implode(', ', array_map(static fn (mixed $v): string => is_scalar($v) ? $field->optionLabel((string) $v) : '', $value));
        }

        return is_scalar($value) ? ($field->type === FieldType::Choice ? $field->optionLabel((string) $value) : (string) $value) : '—';
    }
}
