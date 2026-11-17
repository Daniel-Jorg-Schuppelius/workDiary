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
use CommonToolkit\Helper\Data\{NumberHelper, StringHelper};
use Illuminate\Support\Carbon;

/**
 * Werte zu einem Feldschema (MVP-866): typtreu gespeichert (Zahl → float,
 * Skala → int, Checkbox → bool, Mehrfachauswahl → Liste, Rest → string|null),
 * Upload-/Signaturfelder tragen nur einen Anzeige-Marker (Dateiname,
 * „signed"); der Inhalt liegt als Attachment am Träger.
 *
 * Lange Formulare (Feature 203): Ergänzungen je Frage liegen unter dem
 * reservierten Schlüssel {@see NOTES_KEY}; {@see answeredBySection()} zählt
 * je Abschnitt, {@see ballot()} liefert Auswahlen fürs Papierlayout,
 * {@see rebase()} überträgt Werte auf ein geändertes Schema.
 *
 * @phpstan-type SectionProgress array{key: string, label: string|null, answered: int, total: int}
 */
final class FieldValues {
    /** Ergänzungen je Frage (Feldschlüssel beginnen mit einem Buchstaben, kollidieren also nie). */
    public const string NOTES_KEY = '_notes';

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
                $normalized[$field->key] = StringHelper::parseBool($raw ?? false) ?? false;

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

    /** Beantwortet? Gegenstück zu {@see isEmpty()} (nicht angehakte Checkbox zählt offen). */
    public function isAnswered(FieldDefinition $field): bool {
        return ! $this->isEmpty($field);
    }

    /**
     * Stand je Abschnitt (Feature 203, MVP-1649): sichtbare Fragen und davon
     * beantwortete, in der Reihenfolge von {@see FieldSchema::sections()}.
     *
     * @return list<SectionProgress>
     */
    public function answeredBySection(FieldSchema $schema): array {
        $out = [];
        foreach ($schema->sections() as $section) {
            $answered = 0;
            $total = 0;
            foreach ($section['fields'] as $field) {
                if (! FieldSchema::isVisible($field, $this->values)) {
                    continue;
                }
                $total++;
                if ($this->isAnswered($field)) {
                    $answered++;
                }
            }
            $out[] = ['key' => $section['key'], 'label' => $section['field']?->label, 'answered' => $answered, 'total' => $total];
        }

        return $out;
    }

    /**
     * Werte als Zeichenketten für die Bedingungslogik im Browser (wie
     * {@see FieldSchema::isVisible()}): Liste kommagetrennt, Checkbox "1"/"0".
     *
     * @return array<string, string>
     */
    public function scalars(FieldSchema $schema): array {
        $out = [];
        foreach ($schema as $field) {
            if (! $field->type->hasValue()) {
                continue;
            }
            $value = $this->get($field->key);
            $out[$field->key] = match (true) {
                is_bool($value) => $value ? '1' : '0',
                is_array($value) => implode(',', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value)),
                is_scalar($value) => (string) $value,
                default => $field->type === FieldType::Boolean ? '0' : '',
            };
        }

        return $out;
    }

    /** Ergänzung zu einer Frage (Feature 203, MVP-1651) oder null. */
    public function note(string $key): ?string {
        $note = $this->notes()[$key] ?? null;

        return $note === null || $note === '' ? null : $note;
    }

    /** @return array<string, string> */
    public function notes(): array {
        $raw = $this->values[self::NOTES_KEY] ?? null;
        $notes = [];
        foreach (is_array($raw) ? $raw : [] as $key => $note) {
            if (is_string($note) && trim($note) !== '') {
                $notes[(string) $key] = $note;
            }
        }

        return $notes;
    }

    /** Ergänzung setzen; leer entfernt sie. */
    public function withNote(string $key, ?string $note): self {
        $notes = $this->notes();
        $note = StringHelper::trimToNull($note);
        if ($note === null) {
            unset($notes[$key]);
        } else {
            $notes[$key] = $note;
        }
        $values = $this->values;
        if ($notes === []) {
            unset($values[self::NOTES_KEY]);
        } else {
            $values[self::NOTES_KEY] = $notes;
        }

        return new self($values);
    }

    /**
     * Ergänzungen aus Eingaben übernehmen — nur für Fragen, die sie erlauben
     * oder schon eine tragen (übernommene frühere Antwort); Leeres entfernt.
     *
     * @param  array<array-key, mixed>  $input  Feldschlüssel → Text
     */
    public function withNotesFrom(FieldSchema $schema, array $input): self {
        $values = $this;
        foreach ($schema as $field) {
            if (! array_key_exists($field->key, $input) || (! $field->allowsNote && $this->note($field->key) === null)) {
                continue;
            }
            $raw = $input[$field->key];
            $values = $values->withNote($field->key, is_scalar($raw) ? (string) $raw : null);
        }

        return $values;
    }

    /**
     * Auswahl im Papierlayout (Feature 203, MVP-1651): jede Möglichkeit mit
     * angekreuzt/offen — für Auswahl, Mehrfachauswahl und Checkbox (Ja/Nein);
     * null für alle anderen Typen. Werte außerhalb der Möglichkeiten (frühere
     * Vorlage) stehen angekreuzt dahinter, statt zu verschwinden.
     *
     * @return list<array{label: string, checked: bool}>|null
     */
    public function ballot(FieldDefinition $field): ?array {
        if ($field->extension !== null) {
            return null;
        }
        $value = $this->get($field->key);
        if ($field->type === FieldType::Boolean) {
            return [
                ['label' => (string) __('fields.value.yes'), 'checked' => $value === true],
                ['label' => (string) __('fields.value.no'), 'checked' => $value === false],
            ];
        }
        if (! $field->type->needsOptions()) {
            return null;
        }
        $selected = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (is_scalar($item) && (string) $item !== '') {
                $selected[] = (string) $item;
            }
        }
        $marks = [];
        foreach ($field->options as $option) {
            $marks[] = ['label' => $field->optionLabel($option), 'checked' => in_array($option, $selected, true)];
        }
        foreach (array_values(array_diff($selected, $field->options)) as $extra) {
            $marks[] = ['label' => $extra, 'checked' => true];
        }

        return $marks;
    }

    /**
     * Werte auf ein geändertes Schema übertragen (Feature 203, MVP-1651).
     * Passt eine Antwort noch zur Frage (gleiche oder verträgliche
     * Antwortart, Auswahl unter den Möglichkeiten), bleibt sie; sonst steht
     * sie als Ergänzung an der Frage, statt verloren zu gehen. Fragen, die es
     * nicht mehr gibt, entfallen; neue bleiben offen.
     */
    public function rebase(FieldSchema $from, FieldSchema $to, ?FieldExtensionRegistry $extensions = null): self {
        $result = new self([]);
        foreach ($to as $field) {
            if (! $field->type->hasValue()) {
                continue;
            }
            $note = $this->note($field->key);
            $old = $from->get($field->key);
            if ($old !== null && $this->has($field->key)) {
                [$fits, $value] = self::carry($old, $field, $this->get($field->key));
                if ($fits) {
                    $result = $result->with([$field->key => $value]);
                } elseif (! $this->isEmpty($old)) {
                    $earlier = $this->display($old, $extensions);
                    $note = $note === null ? $earlier : $note . "\n" . $earlier;
                }
            }
            $result = $result->withNote($field->key, $note);
        }

        return $result;
    }

    /**
     * Passt ein Wert von `$from` zur Frage `$to`? Gleiche Antwortart, Text ↔
     * mehrzeiliger Text, Zahl ↔ Messwert, Auswahl ↔ Mehrfachauswahl (eine
     * Möglichkeit); Auswahlen nur unter den neuen Möglichkeiten.
     *
     * @return array{0: bool, 1: mixed}
     */
    private static function carry(FieldDefinition $from, FieldDefinition $to, mixed $value): array {
        if ($value === null || $value === '' || $value === []) {
            return [true, $value];
        }
        if ($from->extension !== $to->extension) {
            return [false, null];
        }
        $pair = $from->type->value . '>' . $to->type->value;
        $sameKind = $from->type === $to->type
            || in_array($pair, ['text>textarea', 'textarea>text', 'number>measurement', 'measurement>number'], true);
        if ($sameKind && ! $to->type->needsOptions()) {
            if ($to->type === FieldType::Text && is_string($value) && mb_strlen($value) > 500) {
                return [false, null];
            }
            if ($to->type === FieldType::Scale && is_numeric($value)
                && ((int) $value < (int) ($to->min ?? 1) || (int) $value > (int) ($to->max ?? 5))) {
                return [false, null];
            }

            return [true, $value];
        }
        if (! $from->type->needsOptions() || ! $to->type->needsOptions()) {
            return [false, null];
        }
        $items = array_values(array_filter(is_array($value) ? $value : [$value], 'is_scalar'));
        $items = array_map(static fn (mixed $v): string => (string) $v, $items);
        if (array_diff($items, $to->options) !== []) {
            return [false, null];
        }
        if ($to->type->isMultiValue()) {
            return [true, $items];
        }

        return count($items) === 1 ? [true, $items[0]] : [false, null];
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
