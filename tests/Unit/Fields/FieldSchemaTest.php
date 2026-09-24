<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldSchemaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Fields;

use App\Enums\Fields\FieldType;
use App\Services\Fields\FieldSchema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Feldschema (MVP-866): Zeilennormalisierung, Altbestand, Bedingungen. */
class FieldSchemaTest extends TestCase {
    private function field(\App\Services\Fields\FieldSchema $schema, string $key): \App\Services\Fields\FieldDefinition {
        $field = $schema->get($key);
        $this->assertNotNull($field);

        return $field;
    }

    public function test_rows_from_the_dialog_become_a_canonical_schema(): void {
        $schema = FieldSchema::fromRows([
            ['label' => 'Bemerkung', 'type' => 'text', 'required' => '1'],
            ['label' => 'Zustand', 'type' => 'select', 'options' => ' gut , mittel , schlecht , gut '],
            ['label' => 'Messwert', 'type' => 'number', 'unit' => 'kWh', 'min' => '0', 'max' => '99.5'],
            ['label' => 'Freigabe', 'type' => 'checkbox'],
            ['label' => 'Bewertung', 'type' => 'scale', 'min' => 1, 'max' => 10],
            ['label' => 'Nur wenn schlecht', 'type' => 'textarea', 'visible_if' => ['field' => 'Zustand', 'op' => 'eq', 'value' => 'schlecht']],
        ]);

        $this->assertSame(['bemerkung', 'zustand', 'messwert', 'freigabe', 'bewertung', 'nur_wenn_schlecht'], $schema->keys());
        $this->assertSame(FieldType::Choice, $this->field($schema, 'zustand')->type);
        $this->assertSame(['gut', 'mittel', 'schlecht'], $this->field($schema, 'zustand')->options);
        $this->assertSame(FieldType::Boolean, $this->field($schema, 'freigabe')->type);
        $this->assertTrue($this->field($schema, 'bemerkung')->required);
        $this->assertSame('kWh', $this->field($schema, 'messwert')->unit);
        $this->assertSame(0, $this->field($schema, 'messwert')->min);
        $this->assertSame(99.5, $this->field($schema, 'messwert')->max);
        $this->assertSame(10, $this->field($schema, 'bewertung')->max);
        $this->assertSame(['field' => 'zustand', 'op' => 'eq', 'value' => 'schlecht'], $this->field($schema, 'nur_wenn_schlecht')->visibleIf, 'Bedingung nennt das Bezugsfeld per Label und wird auf den Key aufgelöst.');

        $row = $schema->toArray()[0];
        $this->assertSame(['key', 'label', 'type', 'required', 'options', 'help', 'unit', 'visible_if'], array_keys($row));
        $this->assertArrayHasKey('min', $schema->toArray()[2]);
        $this->assertArrayNotHasKey('min', $row);
    }

    public function test_stored_definitions_with_former_type_names_are_read(): void {
        $schema = FieldSchema::fromArray([
            ['key' => 'zustand', 'label' => 'Zustand', 'type' => 'select', 'required' => true, 'options' => ['gut'], 'help' => null, 'unit' => null, 'visible_if' => null],
            ['key' => 'ok', 'label' => 'OK', 'type' => 'checkbox', 'required' => false, 'options' => [], 'help' => null, 'unit' => null, 'visible_if' => null],
            ['key' => 'x', 'label' => 'Unbekannt', 'type' => 'sternzeichen'],
            'kaputt',
        ]);

        $this->assertSame(FieldType::Choice, $this->field($schema, 'zustand')->type);
        $this->assertSame(FieldType::Boolean, $this->field($schema, 'ok')->type);
        $this->assertSame(FieldType::Text, $this->field($schema, 'x')->type);
        $this->assertCount(3, $schema);
        $this->assertSame('choice', $schema->toArray()[0]['type']);
    }

    public function test_structural_errors_are_rejected(): void {
        foreach ([
            [[['label' => 'Zustand', 'type' => 'text'], ['label' => 'Zustand', 'type' => 'text']], 'duplicate_key', ['key' => 'zustand']],
            [[['label' => 'Sternzeichen', 'type' => 'sternzeichen']], 'unknown_type', ['row' => 1]],
            [[['label' => 'Zustand', 'type' => 'select', 'options' => '  ']], 'select_needs_options', ['label' => 'Zustand']],
            [[], 'fields_required', []],
            [[['label' => 'A', 'type' => 'text', 'visible_if' => ['field' => 'B', 'op' => 'filled']], ['label' => 'B', 'type' => 'text', 'visible_if' => ['field' => 'A', 'op' => 'filled']]], 'condition_cycle', ['field' => 'a']],
            [[['label' => 'A', 'type' => 'text', 'visible_if' => ['field' => 'nirgends', 'op' => 'eq', 'value' => '1']]], 'condition_unknown_field', ['field' => 'nirgends', 'label' => 'a']],
            [[['label' => 'Skala', 'type' => 'scale', 'min' => 5, 'max' => 1]], 'range_invalid', ['label' => 'Skala']],
        ] as [$rows, $expected, $params]) {
            try {
                FieldSchema::fromRows($rows);
                $this->fail("Erwartet: {$expected}");
            } catch (ValidationException $e) {
                $this->assertSame((string) __("fields.validation.{$expected}", $params), (string) ($e->errors()['fields'][0] ?? ''), $expected);
            }
        }
    }

    public function test_visibility_conditions_are_evaluated_against_values(): void {
        $schema = FieldSchema::fromArray([
            ['key' => 'schaden', 'label' => 'Schaden', 'type' => 'choice', 'options' => ['ja', 'nein']],
            ['key' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'textarea', 'visible_if' => ['field' => 'schaden', 'op' => 'eq', 'value' => 'ja']],
            ['key' => 'kein_schaden', 'label' => 'Kein Schaden', 'type' => 'text', 'visible_if' => ['field' => 'schaden', 'op' => 'ne', 'value' => 'ja']],
            ['key' => 'liste', 'label' => 'Liste', 'type' => 'text', 'visible_if' => ['field' => 'schaden', 'op' => 'in', 'value' => 'ja, vielleicht']],
            ['key' => 'gefuellt', 'label' => 'Gefüllt', 'type' => 'text', 'visible_if' => ['field' => 'haken', 'op' => 'filled', 'value' => '']],
            ['key' => 'haken', 'label' => 'Haken', 'type' => 'boolean'],
        ]);

        $this->assertSame(['schaden', 'beschreibung', 'liste', 'gefuellt', 'haken'], $schema->visibleFor(['schaden' => 'ja', 'haken' => true])->keys());
        $this->assertSame(['schaden', 'kein_schaden', 'haken'], $schema->visibleFor(['schaden' => 'nein', 'haken' => false])->keys());
    }
}
