<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldFormComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fields;

use App\Services\Fields\{FieldSchema, FieldValues};
use Illuminate\Support\{MessageBag, ViewErrorBag};
use Tests\TestCase;

/**
 * `<x-field-form>` und die antwortgerechten Eingaben (Feature 203,
 * MVP-1649/1651): Abschnittskarten mit „x von y beantwortet“, Inhaltsleiste ab
 * vier Abschnitten, eine Karte bis sechs Fragen, Punkt offen/beantwortet,
 * Knöpfe bis sechs Möglichkeiten (fieldset/legend), Ergänzung, Fehler am Feld
 * und kurz oben, Auswahl als ☐/☑ im Papierlayout.
 */
class FieldFormComponentTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        view()->share('errors', new ViewErrorBag);
    }

    /** Fünf Abschnitte à zwei Fragen — ein langer Bogen. */
    private function longSchema(): FieldSchema {
        $rows = [];
        for ($s = 1; $s <= 5; $s++) {
            $rows[] = ['key' => 'abschnitt_' . $s, 'label' => 'Abschnitt ' . $s, 'type' => 'section'];
            $rows[] = ['key' => 'frage_' . $s, 'label' => 'Frage ' . $s, 'type' => 'text'];
            $rows[] = ['key' => 'wahl_' . $s, 'label' => 'Wahl ' . $s, 'type' => 'choice', 'options' => ['ja', 'nein', 'teilweise', 'unklar']];
        }

        return FieldSchema::fromArray($rows);
    }

    public function test_long_form_renders_section_cards_with_progress_and_navigation(): void {
        $html = (string) $this->blade('<x-field-form :schema="$schema" :values="$values" title="Bogen" />', [
            'schema' => $this->longSchema(),
            'values' => FieldValues::fromArray(['frage_1' => 'Antwort', 'wahl_1' => 'ja', 'frage_2' => 'Antwort']),
        ]);

        // Fünf Karten mit Nummer, Titel und Zähler; der Zähler wird im Browser nachgeführt.
        $this->assertSame(5, substr_count($html, 'class="wd-field-card__count"'));
        $this->assertStringContainsString('2 von 2 beantwortet', $html);
        $this->assertStringContainsString('1 von 2 beantwortet', $html);
        $this->assertStringContainsString('0 von 2 beantwortet', $html);
        $this->assertStringContainsString("sectionText('abschnitt_1')", $html);
        // Inhaltsleiste ab vier Abschnitten, mit Sprung und Stand je Abschnitt.
        $this->assertStringContainsString('<nav class="wd-field-nav" aria-label="' . e(__('fields.form.contents')) . '"', $html);
        $this->assertStringContainsString('href="#fld-values-sec-abschnitt_5"', $html);
        $this->assertStringContainsString('wd-field-nav__link--done', $html);
        $this->assertStringContainsString('2/2', $html);
        // Punkt je Frage: beantwortet als Scheibe, offen als Ring.
        $this->assertSame(10, substr_count($html, 'data-field-dot='));
        $this->assertSame(3, substr_count($html, 'wd-field-dot wd-field-dot--done'));
        // Konfiguration für Alpine (CSP): Abschnitte → Feldschlüssel.
        $this->assertStringContainsString('x-data="fieldForm"', $html);
        $this->assertStringContainsString('&quot;abschnitt_3&quot;:[&quot;frage_3&quot;,&quot;wahl_3&quot;]', $html);
    }

    public function test_short_form_stays_one_card_without_navigation(): void {
        $schema = FieldSchema::fromArray([
            ['key' => 'a', 'label' => 'A', 'type' => 'text'],
            ['key' => 'abschnitt', 'label' => 'Teil', 'type' => 'section'],
            ['key' => 'b', 'label' => 'B', 'type' => 'text'],
        ]);

        $html = (string) $this->blade('<x-field-form :schema="$schema" title="Kurz" />', ['schema' => $schema]);

        $this->assertSame(1, substr_count($html, 'class="wd-field-card__count"'));
        $this->assertStringContainsString('0 von 2 beantwortet', $html);
        $this->assertStringNotContainsString('wd-field-nav', $html);
        // Abschnitt bleibt als Zwischenüberschrift.
        $this->assertStringContainsString('Teil', $html);
    }

    public function test_choice_with_up_to_six_options_renders_keyboard_buttons_above_a_select(): void {
        $schema = FieldSchema::fromArray([
            ['key' => 'vier', 'label' => 'Vier', 'type' => 'choice', 'options' => ['a', 'b', 'c', 'd'], 'required' => true],
            ['key' => 'sieben', 'label' => 'Sieben', 'type' => 'choice', 'options' => ['1', '2', '3', '4', '5', '6', '7']],
            ['key' => 'mehr', 'label' => 'Mehr', 'type' => 'multichoice', 'options' => ['x', 'y']],
        ]);

        $four = (string) $this->blade('<x-field-input :field="$field" value="c" />', ['field' => $schema->get('vier')]);
        $this->assertStringContainsString('<fieldset id="fld-values-vier"', $four);
        $this->assertStringContainsString('<legend', $four);
        $this->assertSame(4, substr_count($four, 'type="radio" name="values[vier]"'));
        $this->assertMatchesRegularExpression('/value="c"[^>]*checked/', $four);
        $this->assertStringContainsString('required', $four);
        $this->assertStringNotContainsString('<select', $four);

        $seven = (string) $this->blade('<x-field-input :field="$field" />', ['field' => $schema->get('sieben')]);
        $this->assertStringContainsString('<select name="values[sieben]" id="fld-values-sieben"', $seven);

        $multi = (string) $this->blade('<x-field-input :field="$field" :value="$value" />', ['field' => $schema->get('mehr'), 'value' => ['y']]);
        $this->assertSame(2, substr_count($multi, 'type="checkbox" name="values[mehr][]"'));
        $this->assertStringContainsString('<legend', $multi);
        $this->assertMatchesRegularExpression('/value="y"[^>]*checked/', $multi);
    }

    public function test_inputs_use_theme_tokens_width_by_type_and_offer_notes(): void {
        $schema = FieldSchema::fromArray([
            ['key' => 'lang', 'label' => 'Lang', 'type' => 'textarea', 'help' => 'Ausführlich'],
            ['key' => 'kurz', 'label' => 'Kurz', 'type' => 'date'],
            ['key' => 'stufe', 'label' => 'Stufe', 'type' => 'choice', 'options' => ['a', 'b'], 'allows_note' => true],
        ]);

        $html = (string) $this->blade('<x-field-form :schema="$schema" :values="$values" notes />', [
            'schema' => $schema,
            'values' => FieldValues::fromArray([])->withNote('stufe', 'Hinweis aus dem Gespräch'),
        ]);

        $this->assertStringContainsString('wd-field-control wd-field-control--grow', $html);
        $this->assertMatchesRegularExpression('/class="wd-field sm:col-span-2" data-field="lang"/', $html);
        $this->assertMatchesRegularExpression('/class="wd-field" data-field="kurz"/', $html);
        $this->assertStringContainsString('aria-describedby="fld-values-lang-help"', $html);
        $this->assertStringContainsString('name="notes[stufe]"', $html);
        $this->assertStringContainsString('Hinweis aus dem Gespräch', $html);
        $this->assertStringContainsString(e(__('fields.note.label')), $html);

        // Ohne `notes` (Träger speichert keine Ergänzung, etwa das Portal) gibt es das Feld nicht.
        $plain = (string) $this->blade('<x-field-form :schema="$schema" />', ['schema' => $schema]);
        $this->assertStringNotContainsString('name="notes[stufe]"', $plain);
    }

    public function test_errors_show_at_the_field_and_briefly_on_top(): void {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['values.pflicht' => ['Pflicht fehlt.']])));
        $schema = FieldSchema::fromArray([
            ['key' => 'pflicht', 'label' => 'Pflicht', 'type' => 'text', 'required' => true],
        ]);

        $html = (string) $this->blade('<x-field-form :schema="$schema" />', ['schema' => $schema]);

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('href="#fld-values-pflicht"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="fld-values-pflicht-error"', $html);
        $this->assertStringContainsString('id="fld-values-pflicht-error"', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
    }

    public function test_paper_display_marks_options_and_adds_the_note(): void {
        $schema = FieldSchema::fromArray([
            ['key' => 'wahl', 'label' => 'Wahl', 'type' => 'choice', 'options' => ['gut', 'schlecht']],
        ]);
        $values = FieldValues::fromArray(['wahl' => 'gut'])->withNote('wahl', 'nachgeprüft');

        $paper = (string) $this->blade('<x-field-display :field="$field" :values="$values" paper />', ['field' => $schema->get('wahl'), 'values' => $values]);
        $this->assertStringContainsString('☑ gut', $paper);
        $this->assertStringContainsString('☐ schlecht', $paper);
        $this->assertStringContainsString('nachgeprüft', $paper);

        $screen = (string) $this->blade('<x-field-display :field="$field" :values="$values" />', ['field' => $schema->get('wahl'), 'values' => $values]);
        $this->assertStringNotContainsString('☑', $screen);
        $this->assertStringContainsString('gut', $screen);
    }
}
