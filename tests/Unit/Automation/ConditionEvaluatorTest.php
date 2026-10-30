<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConditionEvaluatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Automation;

use App\Automation\ConditionEvaluator;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase {
    private ConditionEvaluator $eval;

    protected function setUp(): void {
        parent::setUp();
        $this->eval = new ConditionEvaluator();
    }

    public function test_all_group_requires_all_predicates(): void {
        $cond = ['all' => [
            ['field' => 'amount', 'op' => '<=', 'value' => 50],
            ['field' => 'category', 'op' => '=', 'value' => 'taxi'],
        ]];

        $this->assertTrue($this->eval->matches($cond, ['amount' => 30, 'category' => 'taxi']));
        $this->assertFalse($this->eval->matches($cond, ['amount' => 60, 'category' => 'taxi']));
        $this->assertFalse($this->eval->matches($cond, ['amount' => 30, 'category' => 'fuel']));
    }

    public function test_any_group_short_circuits(): void {
        $cond = ['any' => [
            ['field' => 'kind', 'op' => '=', 'value' => 'a'],
            ['field' => 'kind', 'op' => '=', 'value' => 'b'],
        ]];

        $this->assertTrue($this->eval->matches($cond, ['kind' => 'b']));
        $this->assertFalse($this->eval->matches($cond, ['kind' => 'c']));
    }

    public function test_dot_path_traversal(): void {
        $cond = ['field' => 'meta.tag', 'op' => '=', 'value' => 'ok'];
        $this->assertTrue($this->eval->matches($cond, ['meta' => ['tag' => 'ok']]));
    }

    public function test_in_and_not_in(): void {
        $this->assertTrue($this->eval->matches(['field' => 'x', 'op' => 'in', 'value' => [1, 2, 3]], ['x' => 2]));
        $this->assertTrue($this->eval->matches(['field' => 'x', 'op' => 'not_in', 'value' => [1, 2, 3]], ['x' => 9]));
    }

    public function test_contains_and_starts_with(): void {
        $this->assertTrue($this->eval->matches(['field' => 'n', 'op' => 'contains', 'value' => 'foo'], ['n' => 'foobar']));
        $this->assertTrue($this->eval->matches(['field' => 'n', 'op' => 'starts_with', 'value' => 'foo'], ['n' => 'foobar']));
        $this->assertFalse($this->eval->matches(['field' => 'n', 'op' => 'starts_with', 'value' => 'bar'], ['n' => 'foobar']));
    }

    public function test_ends_with_not_contains_and_matches(): void {
        $this->assertTrue($this->eval->matches(['field' => 'mail', 'op' => 'ends_with', 'value' => '@lohn24.de'], ['mail' => 'beleg@lohn24.de']));
        $this->assertFalse($this->eval->matches(['field' => 'mail', 'op' => 'ends_with', 'value' => '@lohn24.de'], ['mail' => 'beleg@lohn24.de.example']));
        $this->assertTrue($this->eval->matches(['field' => 'subject', 'op' => 'not_contains', 'value' => 'Test'], ['subject' => 'Rechnung 4711']));
        $this->assertFalse($this->eval->matches(['field' => 'subject', 'op' => 'not_contains', 'value' => 'Test'], ['subject' => 'Testmail']));
        $this->assertTrue($this->eval->matches(['field' => 'number', 'op' => 'matches', 'value' => '^rzxre\d{7}$'], ['number' => 'RZXRE2500139']));
        $this->assertFalse($this->eval->matches(['field' => 'number', 'op' => 'matches', 'value' => '^rzxre\d{7}$'], ['number' => 'RE-1']));
        // Ein ungültiges Muster trifft nie, statt den Lauf zu stören.
        $this->assertFalse($this->eval->matches(['field' => 'number', 'op' => 'matches', 'value' => '['], ['number' => '[']));
    }
}
