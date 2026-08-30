<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAnswerGraderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Learning;

use App\Services\Learning\LearningAnswerGrader;
use PHPUnit\Framework\TestCase;

/**
 * Bewertungslogik der Prüfungen (Feature 149, MVP-738) — reine Rechenlogik
 * ohne Datenbank, deshalb ein Unit-Test.
 */
class LearningAnswerGraderTest extends TestCase {
    private LearningAnswerGrader $grader;

    protected function setUp(): void {
        parent::setUp();
        $this->grader = new LearningAnswerGrader();
    }

    /**
     * @param  list<array{id: int, is_correct?: bool, position?: int, match_key?: string}>  $options
     * @return array<string, mixed>
     */
    private function question(string $kind, array $options = [], array $settings = [], int $points = 4): array {
        return [
            'id' => 1,
            'kind' => $kind,
            'prompt' => 'Frage',
            'points' => $points,
            'settings' => $settings,
            'options' => array_map(static fn (array $o): array => $o + ['label' => 'Option', 'is_correct' => false, 'position' => 0, 'match_key' => null], $options),
        ];
    }

    public function test_einfachauswahl_zaehlt_nur_die_richtige_option(): void {
        $question = $this->question('single', [
            ['id' => 10, 'is_correct' => true],
            ['id' => 11],
        ]);

        $this->assertSame(['correct' => true, 'points' => 4], $this->grader->grade($question, ['option_ids' => [10]]));
        $this->assertSame(['correct' => false, 'points' => 0], $this->grader->grade($question, ['option_ids' => [11]]));
        $this->assertSame(['correct' => false, 'points' => 0], $this->grader->grade($question, ['option_ids' => [10, 11]]));
    }

    public function test_mehrfachauswahl_ist_ohne_teilpunkte_alles_oder_nichts(): void {
        $question = $this->question('multiple', [
            ['id' => 10, 'is_correct' => true],
            ['id' => 11, 'is_correct' => true],
            ['id' => 12],
        ]);

        $this->assertSame(['correct' => true, 'points' => 4], $this->grader->grade($question, ['option_ids' => [10, 11]]));
        $this->assertSame(['correct' => false, 'points' => 0], $this->grader->grade($question, ['option_ids' => [10]]));
    }

    public function test_mehrfachauswahl_mit_teilpunkten_zieht_falsche_treffer_ab(): void {
        $question = $this->question('multiple', [
            ['id' => 10, 'is_correct' => true],
            ['id' => 11, 'is_correct' => true],
            ['id' => 12],
            ['id' => 13],
        ], ['partial_credit' => true]);

        // Eine von zwei richtig, keine falsche: die Hälfte.
        $this->assertSame(2, $this->grader->grade($question, ['option_ids' => [10]])['points']);
        // Beide richtig plus eine falsche: 1 − 0,5 = 0,5 ⇒ 2 Punkte.
        $this->assertSame(2, $this->grader->grade($question, ['option_ids' => [10, 11, 12]])['points']);
        // Nur falsche: keine Punkte, nie negativ.
        $this->assertSame(0, $this->grader->grade($question, ['option_ids' => [12, 13]])['points']);
    }

    public function test_freitext_vergleicht_ohne_gross_kleinschreibung(): void {
        $question = $this->question('short_text', [], ['answers' => ['Feuerlöscher', 'Loeschdecke']]);

        $this->assertTrue($this->grader->grade($question, ['text' => ' feuerlöscher '])['correct']);
        $this->assertFalse($this->grader->grade($question, ['text' => 'Wasser'])['correct']);
    }

    public function test_freitext_kann_gross_kleinschreibung_erzwingen(): void {
        $question = $this->question('short_text', [], ['answers' => ['DGUV'], 'case_sensitive' => true]);

        $this->assertTrue($this->grader->grade($question, ['text' => 'DGUV'])['correct']);
        $this->assertFalse($this->grader->grade($question, ['text' => 'dguv'])['correct']);
    }

    public function test_lueckentext_gibt_teilpunkte_je_luecke(): void {
        $question = $this->question('cloze', [], ['gaps' => [['rot'], ['grün']]]);

        $full = $this->grader->grade($question, ['gaps' => ['rot', 'grün']]);
        $this->assertTrue($full['correct']);
        $this->assertSame(4, $full['points']);

        $half = $this->grader->grade($question, ['gaps' => ['rot', 'blau']]);
        $this->assertFalse($half['correct']);
        $this->assertSame(2, $half['points'], 'Ein Tippfehler in einer Lücke kostet nicht die ganze Frage.');
    }

    public function test_sortierfrage_prueft_die_gepflegte_reihenfolge(): void {
        $question = $this->question('sort', [
            ['id' => 30, 'position' => 2],
            ['id' => 31, 'position' => 1],
            ['id' => 32, 'position' => 3],
        ]);

        $this->assertTrue($this->grader->grade($question, ['order' => [31, 30, 32]])['correct']);
        $this->assertFalse($this->grader->grade($question, ['order' => [30, 31, 32]])['correct']);
    }

    public function test_zuordnung_zaehlt_richtige_paare(): void {
        $question = $this->question('matching', [
            ['id' => 40, 'match_key' => 'a'],
            ['id' => 41, 'match_key' => 'a'],
            ['id' => 42, 'match_key' => 'b'],
            ['id' => 43, 'match_key' => 'b'],
        ]);

        $full = $this->grader->grade($question, ['pairs' => [40 => 41, 42 => 43]]);
        $this->assertTrue($full['correct']);
        $this->assertSame(4, $full['points']);

        $half = $this->grader->grade($question, ['pairs' => [40 => 41, 42 => 40]]);
        $this->assertFalse($half['correct']);
        $this->assertSame(2, $half['points']);
    }

    public function test_aufsatz_wird_nicht_automatisch_bewertet(): void {
        $question = $this->question('essay');

        $this->assertSame(['correct' => null, 'points' => 0], $this->grader->grade($question, ['text' => 'Lange Antwort']));
    }

    public function test_fehlende_antwort_gilt_als_falsch(): void {
        $question = $this->question('single', [['id' => 10, 'is_correct' => true]]);

        $this->assertSame(['correct' => false, 'points' => 0], $this->grader->grade($question, null));
    }
}
