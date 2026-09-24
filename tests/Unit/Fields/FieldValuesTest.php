<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldValuesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Fields;

use App\Services\Fields\{FieldSchema, FieldValues};
use Tests\TestCase;

/** Wertspeicher (MVP-866): Typtreue und Anzeige je Typ. */
class FieldValuesTest extends TestCase {
    private function field(FieldSchema $schema, string $key): \App\Services\Fields\FieldDefinition {
        $field = $schema->get($key);
        $this->assertNotNull($field);

        return $field;
    }

    private function schema(): FieldSchema {
        return FieldSchema::fromArray([
            ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
            ['key' => 'zahl', 'label' => 'Zahl', 'type' => 'number', 'unit' => 'kWh'],
            ['key' => 'ok', 'label' => 'OK', 'type' => 'boolean'],
            ['key' => 'mehr', 'label' => 'Mehr', 'type' => 'multichoice', 'options' => ['x', 'y']],
            ['key' => 'datum', 'label' => 'Datum', 'type' => 'date'],
            ['key' => 'zeit', 'label' => 'Zeit', 'type' => 'datetime'],
            ['key' => 'skala', 'label' => 'Skala', 'type' => 'scale', 'max' => 10],
            ['key' => 'unterschrift', 'label' => 'Unterschrift', 'type' => 'signature'],
            ['key' => 'abschnitt', 'label' => 'Abschnitt', 'type' => 'section'],
            ['key' => 'messung', 'label' => 'Messung', 'type' => 'measurement', 'unit' => 'bar'],
        ]);
    }

    public function test_normalize_stores_type_true_values_and_drops_unknown_keys(): void {
        $values = FieldValues::normalize($this->schema(), [
            'text' => ' Hallo ', 'zahl' => '42.5', 'ok' => '1', 'mehr' => ['x', 'y', 3], 'datum' => '2026-09-24',
            'zeit' => '2026-09-24T10:30', 'skala' => '7', 'unterschrift' => 'signed', 'abschnitt' => 'egal', 'messung' => '', 'fremd' => 'weg',
        ])->toArray();

        $this->assertSame(' Hallo ', $values['text']);
        $this->assertSame(42.5, $values['zahl']);
        $this->assertTrue($values['ok']);
        $this->assertSame(['x', 'y', '3'], $values['mehr']);
        $this->assertSame(7, $values['skala']);
        $this->assertSame('signed', $values['unterschrift']);
        $this->assertNull($values['messung']);
        $this->assertArrayNotHasKey('abschnitt', $values);
        $this->assertArrayNotHasKey('fremd', $values);
        $this->assertFalse(FieldValues::normalize($this->schema(), [])->get('ok'), 'Checkbox ohne Eingabe ist false, nicht null.');
    }

    public function test_display_formats_each_type(): void {
        $schema = $this->schema();
        $values = FieldValues::normalize($schema, [
            'text' => 'Hallo', 'zahl' => '1234.5', 'ok' => '1', 'mehr' => ['x', 'y'], 'datum' => '2026-09-24',
            'zeit' => '2026-09-24T10:30:00', 'skala' => '7', 'unterschrift' => 'signed', 'messung' => '3',
        ]);

        $this->assertSame('Hallo', $values->display($this->field($schema, 'text')));
        $this->assertSame('1.234,5 kWh', $values->display($this->field($schema, 'zahl')));
        $this->assertSame((string) __('fields.value.yes'), $values->display($this->field($schema, 'ok')));
        $this->assertSame('x, y', $values->display($this->field($schema, 'mehr')));
        $this->assertSame('24.09.2026', $values->display($this->field($schema, 'datum')));
        $this->assertStringStartsWith('24.09.2026', $values->display($this->field($schema, 'zeit')));
        $this->assertSame('7 / 10', $values->display($this->field($schema, 'skala')));
        $this->assertSame((string) __('fields.value.signed'), $values->display($this->field($schema, 'unterschrift')));
        $this->assertSame('3 bar', $values->display($this->field($schema, 'messung')));
        $this->assertSame('—', FieldValues::fromArray([])->display($this->field($schema, 'text')));
        $this->assertSame((string) __('fields.value.no'), FieldValues::fromArray([])->display($this->field($schema, 'ok')));
    }
}
