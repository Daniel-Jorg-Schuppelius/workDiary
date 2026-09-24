<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldComponentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fields;

use App\Enums\Fields\FieldType;
use App\Services\Fields\{FieldDefinition, FieldSchema, FieldValues};
use Tests\TestCase;

/** Blade-Komponenten des Feldschema-Bausteins (MVP-866): Eingabe und Anzeige je Typ. */
class FieldComponentsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        // Ohne Request-Zyklus teilt keine Middleware den Fehlerbeutel — @error braucht ihn.
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }

    private function schema(): FieldSchema {
        return FieldSchema::fromArray([
            ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'required' => true, 'help' => 'Hilfe zum Text'],
            ['key' => 'lang', 'label' => 'Lang', 'type' => 'textarea'],
            ['key' => 'zahl', 'label' => 'Zahl', 'type' => 'number', 'unit' => 'kWh', 'min' => 0, 'max' => 10],
            ['key' => 'ok', 'label' => 'OK', 'type' => 'boolean'],
            ['key' => 'wahl', 'label' => 'Wahl', 'type' => 'choice', 'options' => ['a', 'b']],
            ['key' => 'mehr', 'label' => 'Mehr', 'type' => 'multichoice', 'options' => ['x', 'y']],
            ['key' => 'datum', 'label' => 'Datum', 'type' => 'date'],
            ['key' => 'zeit', 'label' => 'Zeit', 'type' => 'datetime'],
            ['key' => 'skala', 'label' => 'Skala', 'type' => 'scale', 'min' => 1, 'max' => 3],
            ['key' => 'foto', 'label' => 'Foto', 'type' => 'photo'],
            ['key' => 'datei', 'label' => 'Datei', 'type' => 'file'],
            ['key' => 'unterschrift', 'label' => 'Unterschrift', 'type' => 'signature'],
            ['key' => 'abschnitt', 'label' => 'Abschnitt', 'type' => 'section'],
            ['key' => 'messung', 'label' => 'Messung', 'type' => 'measurement', 'unit' => 'bar'],
        ]);
    }

    public function test_every_type_renders_an_input_bound_to_its_channel(): void {
        $expectations = [
            'text' => 'name="values[text]"',
            'lang' => '<textarea name="values[lang]"',
            'zahl' => 'type="number"',
            'ok' => 'type="checkbox" name="values[ok]"',
            'wahl' => '<select name="values[wahl]"',
            'mehr' => 'name="values[mehr][]"',
            'datum' => 'type="date"',
            'zeit' => 'type="datetime-local"',
            'skala' => 'type="radio" name="values[skala]" value="3"',
            'foto' => 'name="files[foto]"',
            'datei' => 'name="files[datei]"',
            'unterschrift' => 'name="signatures[unterschrift]"',
            'abschnitt' => '<h3',
            'messung' => 'type="number"',
        ];
        foreach ($this->schema() as $field) {
            $html = (string) $this->blade('<x-field-input :field="$field" :value="$value" />', ['field' => $field, 'value' => $field->key === 'mehr' ? ['x'] : '']);
            $this->assertStringContainsString($expectations[$field->key], $html, $field->key);
            $this->assertStringContainsString($field->label, $html, $field->key);
        }
        $required = (string) $this->blade('<x-field-input :field="$field" />', ['field' => $this->schema()->get('text')]);
        $this->assertStringContainsString('Text *', $required);
        $this->assertStringContainsString('Hilfe zum Text', $required);
        $this->assertStringContainsString('required', $required);
        $withUnit = (string) $this->blade('<x-field-input :field="$field" />', ['field' => $this->schema()->get('zahl')]);
        $this->assertStringContainsString('Zahl (kWh)', $withUnit);
        $this->assertStringContainsString('min="0"', $withUnit);
        $this->assertStringContainsString('max="10"', $withUnit);
    }

    public function test_display_shows_formatted_values(): void {
        $schema = $this->schema();
        $values = FieldValues::normalize($schema, ['zahl' => '2.5', 'ok' => '1', 'mehr' => ['x', 'y'], 'datum' => '2026-09-24', 'skala' => '2', 'unterschrift' => 'signed']);
        $expect = [
            'zahl' => '2,5 kWh', 'ok' => (string) __('fields.value.yes'), 'mehr' => 'x, y', 'datum' => '24.09.2026',
            'skala' => '2 / 3', 'unterschrift' => (string) __('fields.value.signed'), 'text' => '—',
        ];
        foreach ($expect as $key => $text) {
            $html = (string) $this->blade('<x-field-display :field="$field" :values="$values" />', ['field' => $schema->get($key), 'values' => $values]);
            $this->assertStringContainsString($text, $html, $key);
        }
    }

    public function test_field_type_catalog_is_labelled_in_every_locale(): void {
        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            app()->setLocale($locale);
            foreach (FieldType::cases() as $type) {
                $this->assertNotSame('enums.fields.type.' . $type->value, $type->label(), "{$locale}: {$type->value}");
            }
        }
        app()->setLocale('de');
        $this->assertSame('Auswahl', FieldType::Choice->label());
        $this->assertSame(FieldType::Choice, FieldType::fromStored('select'));
        $this->assertSame(FieldType::Boolean, FieldType::fromStored('checkbox'));
        $this->assertNull(FieldType::fromStored('sternzeichen'));
        $this->assertInstanceOf(FieldDefinition::class, $this->schema()->get('text'));
    }
}
