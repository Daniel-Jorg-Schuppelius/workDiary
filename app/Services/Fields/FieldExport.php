<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

/**
 * Tabellenspalten je Felddefinition (MVP-866): eine Spalte je Feld mit Wert
 * (Abschnitte entfallen), Anzeigewerte wie in Show und PDF — für CSV/XLSX
 * über die Toolkit-Writer.
 */
class FieldExport {
    public function __construct(private readonly FieldExtensionRegistry $extensions) {}

    /** @return list<array{key: string, label: string}> */
    public function columns(FieldSchema $schema): array {
        $columns = [];
        foreach ($schema as $field) {
            if ($field->type->hasValue()) {
                $columns[] = ['key' => $field->key, 'label' => $field->label . ($field->unit !== null ? " ({$field->unit})" : '')];
            }
        }

        return $columns;
    }

    /** @return list<string> Zellen in Spaltenreihenfolge von {@see columns()}. */
    public function row(FieldSchema $schema, FieldValues $values): array {
        $cells = [];
        foreach ($schema as $field) {
            if ($field->type->hasValue()) {
                $cells[] = $values->isEmpty($field) && $field->type !== \App\Enums\Fields\FieldType::Boolean ? '' : $values->display($field, $this->extensions);
            }
        }

        return $cells;
    }
}
