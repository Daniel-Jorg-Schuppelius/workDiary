<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConditionEvaluator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Automation;

use Illuminate\Support\Arr;

/**
 * Wertet eine Bedingungs-DSL gegen einen flachen Kontext aus.
 *
 * Grammatik:
 *   condition := group | predicate
 *   group     := { "all": [condition, …] } | { "any": [condition, …] }
 *   predicate := { "field": "<dot.path>", "op": "<op>", "value": <scalar|array> }
 *
 * Operatoren: =, !=, <, <=, >, >=, in, not_in, contains, starts_with
 */
class ConditionEvaluator {
    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    public function matches(array $condition, array $context): bool {
        if (array_key_exists('all', $condition)) {
            foreach ((array) $condition['all'] as $sub) {
                if (! $this->matches((array) $sub, $context)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $condition)) {
            foreach ((array) $condition['any'] as $sub) {
                if ($this->matches((array) $sub, $context)) {
                    return true;
                }
            }

            return false;
        }

        return $this->evaluatePredicate($condition, $context);
    }

    /**
     * @param  array<string, mixed>  $predicate
     * @param  array<string, mixed>  $context
     */
    private function evaluatePredicate(array $predicate, array $context): bool {
        $field = (string) ($predicate['field'] ?? '');
        $op = (string) ($predicate['op'] ?? '=');
        $expected = $predicate['value'] ?? null;
        $actual = Arr::get($context, $field);

        return match ($op) {
            '=', '==' => $this->loose($actual) === $this->loose($expected),
            '!=', '<>' => $this->loose($actual) !== $this->loose($expected),
            '<' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            '<=' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            '>' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            '>=' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'in' => is_array($expected) && in_array($this->loose($actual), array_map([$this, 'loose'], $expected), true),
            'not_in' => is_array($expected) && ! in_array($this->loose($actual), array_map([$this, 'loose'], $expected), true),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'starts_with' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            default => false,
        };
    }

    private function loose(mixed $value): mixed {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }
}
