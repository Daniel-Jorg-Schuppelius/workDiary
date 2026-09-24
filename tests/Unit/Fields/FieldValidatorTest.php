<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldValidatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Fields;

use App\Services\Fields\{FieldSchema, FieldValidator};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Regeln je Feldtyp (MVP-866). */
class FieldValidatorTest extends TestCase {
    private function schema(): FieldSchema {
        return FieldSchema::fromArray([
            ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'required' => true],
            ['key' => 'lang', 'label' => 'Lang', 'type' => 'textarea'],
            ['key' => 'zahl', 'label' => 'Zahl', 'type' => 'number', 'min' => 0, 'max' => 10],
            ['key' => 'ok', 'label' => 'OK', 'type' => 'boolean', 'required' => true],
            ['key' => 'wahl', 'label' => 'Wahl', 'type' => 'choice', 'options' => ['a', 'b']],
            ['key' => 'mehr', 'label' => 'Mehr', 'type' => 'multichoice', 'options' => ['x', 'y'], 'required' => true],
            ['key' => 'datum', 'label' => 'Datum', 'type' => 'date'],
            ['key' => 'zeit', 'label' => 'Zeit', 'type' => 'datetime'],
            ['key' => 'skala', 'label' => 'Skala', 'type' => 'scale', 'min' => 1, 'max' => 5],
            ['key' => 'foto', 'label' => 'Foto', 'type' => 'photo', 'required' => true],
            ['key' => 'unterschrift', 'label' => 'Unterschrift', 'type' => 'signature', 'required' => true],
            ['key' => 'abschnitt', 'label' => 'Abschnitt', 'type' => 'section'],
            ['key' => 'messung', 'label' => 'Messung', 'type' => 'measurement', 'unit' => 'bar', 'max' => 16],
        ]);
    }

    public function test_rules_follow_the_type_and_required_flag(): void {
        $rules = app(FieldValidator::class)->rules($this->schema());

        $this->assertSame(['required', 'string', 'max:500'], $rules['values.text']);
        $this->assertSame(['nullable', 'string', 'max:10000'], $rules['values.lang']);
        $this->assertSame(['nullable', 'numeric', 'min:0', 'max:10'], $rules['values.zahl']);
        $this->assertSame(['accepted'], $rules['values.ok']);
        $this->assertSame(['required', 'array', 'min:1'], $rules['values.mehr']);
        $this->assertSame('string', $rules['values.mehr.*'][0]);
        $this->assertSame('nullable', $rules['values.datum'][0]);
        $this->assertInstanceOf(\Closure::class, $rules['values.datum'][1], 'Datum strikt (kein „tomorrow"), siehe DateHelper::isDateTime.');
        $this->assertInstanceOf(\Closure::class, $rules['values.zeit'][1]);
        $this->assertSame(['nullable', 'integer', 'min:1', 'max:5'], $rules['values.skala']);
        $this->assertSame(['nullable', 'string', 'max:500'], $rules['values.foto'], 'Uploads tragen nur einen Marker im Werte-Array.');
        $this->assertArrayNotHasKey('values.abschnitt', $rules);
        $this->assertSame(['nullable', 'numeric', 'max:16'], $rules['values.messung']);

        $ok = Validator::make(['values' => ['text' => 'x', 'ok' => '1', 'wahl' => 'a', 'mehr' => ['x'], 'skala' => 3, 'zahl' => 5, 'datum' => '2026-09-24', 'zeit' => '2026-09-24T10:30']], $rules);
        $this->assertTrue($ok->passes(), implode(', ', $ok->errors()->all()));
        $bad = Validator::make(['values' => ['text' => '', 'ok' => '0', 'wahl' => 'c', 'mehr' => ['z'], 'skala' => 9, 'zahl' => 11, 'datum' => 'tomorrow']], $rules);
        $this->assertEqualsCanonicalizing(['values.text', 'values.zahl', 'values.ok', 'values.wahl', 'values.mehr.0', 'values.skala', 'values.datum'], array_keys($bad->errors()->toArray()));
    }

    public function test_missing_attachments_are_reported_per_field(): void {
        $validator = app(FieldValidator::class);
        $missing = $validator->missingAttachments($this->schema(), [], []);
        $this->assertSame(['values.foto', 'values.unterschrift'], array_keys($missing));

        $present = $validator->missingAttachments($this->schema(), ['foto' => UploadedFile::fake()->image('a.jpg')], ['unterschrift' => 'data:image/png;base64,AAA']);
        $this->assertSame([], $present);
        $deferred = $validator->missingAttachments($this->schema(), [], ['unterschrift' => 'x'], ['foto']);
        $this->assertSame([], $deferred, 'Offline nachgereichte Dateien gelten als vorhanden.');
    }
}
