<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Enums\Fields\FieldType;
use Illuminate\Support\Str;

/**
 * Schema und Werte in einem Dokument (MVP-866) — die Form einer Checkliste
 * in einer JSON-Spalte (`{schema: [...], values: {...}}`). Liest die
 * bisherigen Freiformen (`[{label, done}]`, `{label: bool}`, `[label, …]`)
 * und schreibt nur noch die Kanonik.
 *
 * @phpstan-import-type DefinitionArray from FieldDefinition
 */
final class FieldDocument {
    public function __construct(
        public readonly FieldSchema $schema,
        public readonly FieldValues $values,
    ) {}

    /**
     * Checkliste aus Bezeichnungen; `$done` nennt erledigte Einträge (Key oder Label).
     *
     * @param  array<array-key, string>  $labels
     * @param  array<array-key, string>  $done
     */
    public static function checklist(array $labels, array $done = []): self {
        $fields = [];
        $values = [];
        $seen = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $key = self::uniqueKey($label, $seen);
            $fields[] = new FieldDefinition($key, FieldType::Boolean, $label);
            $values[$key] = in_array($key, $done, true) || in_array($label, $done, true);
        }

        return new self(new FieldSchema($fields), new FieldValues($values));
    }

    /** Gespeicherter oder eingegebener Wert → Dokument; null für leer. */
    public static function fromStored(mixed $raw): ?self {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        if (! is_array($raw)) {
            return null;
        }
        if (isset($raw['schema']) && is_array($raw['schema'])) {
            return new self(FieldSchema::fromArray($raw['schema']), FieldValues::fromArray($raw['values'] ?? []));
        }

        return self::fromLegacy($raw);
    }

    /**
     * @return array{schema: list<DefinitionArray>, values: array<string, mixed>}
     */
    public function toArray(): array {
        return ['schema' => $this->schema->toArray(), 'values' => $this->values->toArray()];
    }

    /** @return list<array{field: FieldDefinition, value: mixed}> */
    public function items(): array {
        $items = [];
        foreach ($this->schema as $field) {
            $items[] = ['field' => $field, 'value' => $this->values->get($field->key)];
        }

        return $items;
    }

    public function isEmpty(): bool {
        return $this->schema->isEmpty();
    }

    /**
     * Anzahl erledigter Checkbox-Einträge und Gesamtzahl.
     *
     * @return array{done: int, total: int}
     */
    public function progress(): array {
        $total = 0;
        $done = 0;
        foreach ($this->schema as $field) {
            if ($field->type !== FieldType::Boolean) {
                continue;
            }
            $total++;
            if ($this->values->get($field->key) === true) {
                $done++;
            }
        }

        return ['done' => $done, 'total' => $total];
    }

    /** @param  array<int|string, mixed>  $raw */
    private static function fromLegacy(array $raw): self {
        $labels = [];
        $done = [];
        foreach ($raw as $key => $entry) {
            if (is_array($entry)) {
                $label = trim((string) ($entry['label'] ?? $entry['text'] ?? $entry['key'] ?? (is_string($key) ? $key : '')));
                if ($label === '') {
                    continue;
                }
                $labels[] = $label;
                if (filter_var($entry['done'] ?? $entry['checked'] ?? $entry['value'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $done[] = $label;
                }

                continue;
            }
            if (is_string($key)) {
                $labels[] = $key;
                if (filter_var($entry, FILTER_VALIDATE_BOOL)) {
                    $done[] = $key;
                }

                continue;
            }
            if (is_scalar($entry) && trim((string) $entry) !== '') {
                $labels[] = trim((string) $entry);
            }
        }

        return self::checklist($labels, $done);
    }

    /** @param  array<string, true>  $seen */
    private static function uniqueKey(string $label, array &$seen): string {
        $base = Str::slug(Str::limit($label, 50, ''), '_');
        if ($base === '' || preg_match('/^[a-z]/', $base) !== 1) {
            $base = 'item_' . $base;
        }
        $key = $base;
        for ($i = 2; isset($seen[$key]); $i++) {
            $key = $base . '_' . $i;
        }
        $seen[$key] = true;

        return $key;
    }
}
