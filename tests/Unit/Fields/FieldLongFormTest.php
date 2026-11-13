<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldLongFormTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Fields;

use App\Enums\Form\FormSubmissionStatus;
use App\Services\Fields\{FieldDefinition, FieldLayout, FieldSchema, FieldValues};
use Tests\TestCase;

/**
 * Lange Formulare (Feature 203): Gliederung an Abschnitten, Zählung je
 * Abschnitt, Ergänzungen, Auswahl im Papierlayout, Übertragen auf ein
 * geändertes Schema und die Gestaltungsgrenzen an einer Stelle.
 */
class FieldLongFormTest extends TestCase {
    private function schema(): FieldSchema {
        return FieldSchema::fromArray([
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'person', 'label' => 'Person', 'type' => 'section'],
            ['key' => 'alter', 'label' => 'Alter', 'type' => 'number'],
            ['key' => 'ausweis', 'label' => 'Ausweis geprüft', 'type' => 'boolean'],
            ['key' => 'risiko', 'label' => 'Risiko', 'type' => 'section'],
            ['key' => 'stufe', 'label' => 'Stufe', 'type' => 'choice', 'options' => ['gering', 'mittel', 'hoch'], 'allows_note' => true],
            ['key' => 'grund', 'label' => 'Grund', 'type' => 'textarea', 'visible_if' => ['field' => 'stufe', 'op' => 'eq', 'value' => 'hoch']],
            ['key' => 'leer', 'label' => 'Leer', 'type' => 'section'],
        ]);
    }

    public function test_sections_split_at_section_fields_with_a_headless_start(): void {
        $sections = $this->schema()->sections();

        $this->assertSame([FieldSchema::START_SECTION, 'person', 'risiko', 'leer'], array_column($sections, 'key'));
        $this->assertNull($sections[0]['field']);
        $this->assertSame(['name'], array_map(static fn (FieldDefinition $f): string => $f->key, $sections[0]['fields']));
        $this->assertSame(['alter', 'ausweis'], array_map(static fn (FieldDefinition $f): string => $f->key, $sections[1]['fields']));
        $this->assertSame([], $sections[3]['fields']);
        $this->assertSame(['ausweis'], $this->schema()->booleanKeys());
    }

    public function test_answered_by_section_counts_visible_questions_and_open_checkboxes(): void {
        $values = FieldValues::fromArray(['name' => 'Muster', 'alter' => 0.0, 'ausweis' => false, 'stufe' => 'gering', 'grund' => 'egal']);

        $progress = $values->answeredBySection($this->schema());

        $this->assertSame([
            ['key' => FieldSchema::START_SECTION, 'label' => null, 'answered' => 1, 'total' => 1],
            // Zahl 0 ist beantwortet, eine nicht angehakte Checkbox offen.
            ['key' => 'person', 'label' => 'Person', 'answered' => 1, 'total' => 2],
            // „Grund“ ist nur bei hohem Risiko sichtbar und zählt sonst nicht.
            ['key' => 'risiko', 'label' => 'Risiko', 'answered' => 1, 'total' => 1],
            ['key' => 'leer', 'label' => 'Leer', 'answered' => 0, 'total' => 0],
        ], $progress);

        $high = FieldValues::fromArray(['stufe' => 'hoch'])->answeredBySection($this->schema());
        $this->assertSame(['answered' => 1, 'total' => 2], ['answered' => $high[2]['answered'], 'total' => $high[2]['total']]);
    }

    public function test_scalars_mirror_the_browser_values(): void {
        $scalars = FieldValues::fromArray(['ausweis' => true, 'stufe' => 'hoch', 'alter' => 42.0])->scalars($this->schema());

        $this->assertSame('1', $scalars['ausweis']);
        $this->assertSame('hoch', $scalars['stufe']);
        $this->assertSame('42', $scalars['alter']);
        $this->assertSame('', $scalars['name']);
        $this->assertArrayNotHasKey('person', $scalars);
        $this->assertSame('0', FieldValues::fromArray([])->scalars($this->schema())['ausweis']);
    }

    public function test_notes_are_kept_under_a_reserved_key_and_only_where_allowed(): void {
        $schema = $this->schema();
        $values = FieldValues::fromArray(['stufe' => 'mittel'])
            ->withNotesFrom($schema, ['stufe' => '  laut Gespräch  ', 'name' => 'nicht erlaubt']);

        $this->assertSame('laut Gespräch', $values->note('stufe'));
        $this->assertNull($values->note('name'));
        $this->assertSame(['stufe' => 'laut Gespräch'], $values->toArray()[FieldValues::NOTES_KEY]);
        // Feldschlüssel beginnen mit einem Buchstaben — der reservierte Schlüssel kollidiert nie.
        $this->assertSame(0, preg_match(FieldSchema::KEY_PATTERN, FieldValues::NOTES_KEY));

        $cleared = $values->withNotesFrom($schema, ['stufe' => '']);
        $this->assertNull($cleared->note('stufe'));
        $this->assertArrayNotHasKey(FieldValues::NOTES_KEY, $cleared->toArray());
    }

    public function test_ballot_marks_every_option_for_paper(): void {
        $schema = FieldSchema::fromArray([
            ['key' => 'wahl', 'label' => 'Wahl', 'type' => 'choice', 'options' => ['a', 'b']],
            ['key' => 'mehr', 'label' => 'Mehr', 'type' => 'multichoice', 'options' => ['x', 'y', 'z']],
            ['key' => 'ok', 'label' => 'OK', 'type' => 'boolean'],
            ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
        ]);
        $values = FieldValues::fromArray(['wahl' => 'b', 'mehr' => ['x', 'alt'], 'ok' => false]);

        $this->assertSame([['label' => 'a', 'checked' => false], ['label' => 'b', 'checked' => true]], $values->ballot($this->field($schema, 'wahl')));
        $this->assertSame([
            ['label' => 'x', 'checked' => true], ['label' => 'y', 'checked' => false], ['label' => 'z', 'checked' => false],
            // Wert einer früheren Vorlage verschwindet nicht.
            ['label' => 'alt', 'checked' => true],
        ], $values->ballot($this->field($schema, 'mehr')));
        $this->assertSame([false, true], array_column((array) $values->ballot($this->field($schema, 'ok')), 'checked'));
        $this->assertSame([false, false], array_column((array) FieldValues::fromArray([])->ballot($this->field($schema, 'ok')), 'checked'));
        $this->assertNull($values->ballot($this->field($schema, 'text')));
    }

    public function test_rebase_keeps_fitting_answers_and_turns_the_rest_into_notes(): void {
        $from = FieldSchema::fromArray([
            ['key' => 'zustand', 'label' => 'Zustand', 'type' => 'text'],
            ['key' => 'menge', 'label' => 'Menge', 'type' => 'number'],
            ['key' => 'farbe', 'label' => 'Farbe', 'type' => 'choice', 'options' => ['rot', 'blau']],
            ['key' => 'weg', 'label' => 'Entfällt', 'type' => 'text'],
            ['key' => 'notiz', 'label' => 'Notiz', 'type' => 'text'],
        ]);
        $to = FieldSchema::fromArray([
            ['key' => 'zustand', 'label' => 'Zustand', 'type' => 'choice', 'options' => ['gut', 'schlecht']],
            ['key' => 'menge', 'label' => 'Menge', 'type' => 'measurement', 'unit' => 'kg'],
            ['key' => 'farbe', 'label' => 'Farben', 'type' => 'multichoice', 'options' => ['rot', 'blau', 'grün']],
            ['key' => 'notiz', 'label' => 'Notiz', 'type' => 'textarea'],
            ['key' => 'neu', 'label' => 'Neu', 'type' => 'text'],
        ]);
        $values = FieldValues::fromArray(['zustand' => 'leicht beschädigt', 'menge' => 2.5, 'farbe' => 'blau', 'weg' => 'x', 'notiz' => 'bleibt']);

        $rebased = $values->rebase($from, $to);

        // Antwortart gewechselt (Text → Auswahl ohne passende Möglichkeit): als Ergänzung erhalten.
        $this->assertNull($rebased->get('zustand'));
        $this->assertSame('leicht beschädigt', $rebased->note('zustand'));
        // Verträgliche Wechsel bleiben Werte.
        $this->assertSame(2.5, $rebased->get('menge'));
        $this->assertSame(['blau'], $rebased->get('farbe'));
        $this->assertSame('bleibt', $rebased->get('notiz'));
        // Entfallene Fragen fallen weg, neue bleiben offen.
        $this->assertFalse($rebased->has('weg'));
        $this->assertFalse($rebased->has('neu'));
    }

    public function test_allows_note_round_trips_and_only_choice_questions_get_it_from_the_editor(): void {
        $definition = FieldDefinition::fromArray(['key' => 'stufe', 'label' => 'Stufe', 'type' => 'choice', 'options' => ['a'], 'allows_note' => true]);
        $this->assertTrue($definition->allowsNote);
        $this->assertTrue($definition->toArray()['allows_note'] ?? false);
        $this->assertArrayNotHasKey('allows_note', FieldDefinition::fromArray(['key' => 'x', 'label' => 'X', 'type' => 'text'])->toArray(), 'Bestand bleibt byte-gleich');
        $this->assertFalse($definition->optional()->required);
        $this->assertTrue($definition->optional()->allowsNote);
        $this->assertSame(['a'], $definition->optional()->with(required: true)->options);

        $schema = FieldSchema::fromRows([
            ['label' => 'Stufe', 'type' => 'choice', 'options' => 'a, b', 'allows_note' => '1'],
            ['label' => 'Text', 'type' => 'text', 'allows_note' => '1'],
            // Bedingung über das Label: die Kopie behält Ergänzung und Listen-Spalte.
            ['label' => 'Folge', 'type' => 'multichoice', 'options' => 'x', 'allows_note' => '1', 'listed' => '1', 'visible_if' => ['field' => 'Stufe', 'op' => 'eq', 'value' => 'a']],
        ]);
        $this->assertTrue($this->field($schema, 'stufe')->allowsNote);
        $this->assertFalse($this->field($schema, 'text')->allowsNote);
        $this->assertTrue($this->field($schema, 'folge')->allowsNote);
        $this->assertTrue($this->field($schema, 'folge')->listed);
        $this->assertSame('stufe', $this->field($schema, 'folge')->visibleIf['field'] ?? null);
        $this->assertTrue($schema->toRows()[0]['allows_note']);
    }

    public function test_layout_limits_live_in_one_place(): void {
        $choice = static fn (int $n): FieldDefinition => FieldDefinition::fromArray(['key' => 'c', 'label' => 'C', 'type' => 'choice', 'options' => array_map(static fn (int $i): string => 'o' . $i, range(1, $n))]);

        $this->assertSame(6, FieldLayout::CHOICE_BUTTONS_MAX);
        $this->assertTrue(FieldLayout::choiceAsButtons($choice(4)));
        $this->assertTrue(FieldLayout::choiceAsButtons($choice(6)));
        $this->assertFalse(FieldLayout::choiceAsButtons($choice(7)));
        $this->assertTrue(FieldLayout::fullWidth($choice(4)));
        $this->assertFalse(FieldLayout::fullWidth($choice(7)));
        $this->assertTrue(FieldLayout::fullWidth(FieldDefinition::fromArray(['key' => 't', 'label' => 'T', 'type' => 'textarea'])));
        $this->assertFalse(FieldLayout::fullWidth(FieldDefinition::fromArray(['key' => 'd', 'label' => 'D', 'type' => 'date'])));

        // Bis sechs Fragen eine Karte; Abschnittskarten erst darüber und nur mit Abschnitten.
        $this->assertFalse(FieldLayout::usesCards($this->schema()));
        $long = $this->longSchema(5);
        $this->assertTrue(FieldLayout::isLong($long));
        $this->assertTrue(FieldLayout::usesCards($long));
        $this->assertTrue(FieldLayout::showsNavigation($long));
        $this->assertFalse(FieldLayout::showsNavigation($this->longSchema(3)));
    }

    public function test_submission_status_only_moves_from_draft_to_submitted(): void {
        $this->assertTrue(FormSubmissionStatus::Draft->canTransitionTo(FormSubmissionStatus::Submitted));
        $this->assertFalse(FormSubmissionStatus::Submitted->canTransitionTo(FormSubmissionStatus::Draft));
        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            app()->setLocale($locale);
            foreach (FormSubmissionStatus::cases() as $case) {
                $this->assertStringNotContainsString('enums.', $case->label(), "{$locale}: {$case->value}");
            }
        }
        app()->setLocale('de');
    }

    private function field(FieldSchema $schema, string $key): FieldDefinition {
        $field = $schema->get($key);
        $this->assertNotNull($field);

        return $field;
    }

    private function longSchema(int $sections): FieldSchema {
        $rows = [];
        for ($s = 1; $s <= $sections; $s++) {
            $rows[] = ['key' => 's' . $s, 'label' => 'Abschnitt ' . $s, 'type' => 'section'];
            $rows[] = ['key' => 'a' . $s, 'label' => 'Frage A' . $s, 'type' => 'text'];
            $rows[] = ['key' => 'b' . $s, 'label' => 'Frage B' . $s, 'type' => 'text'];
        }

        return FieldSchema::fromArray($rows);
    }
}
