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

use CommonToolkit\ValueObjects\{Decimal, Money, Percentage};
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
        $actual = $this->scalar(Arr::get($context, $field));

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
            'ends_with' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            'not_contains' => is_string($actual) && is_string($expected) && ! str_contains($actual, $expected),
            'matches' => is_string($actual) && is_string($expected) && $this->matchesPattern($actual, $expected),
            default => false,
        };
    }

    /**
     * Value Objects auf ihren Zahlwert bringen — Regeln vergleichen Beträge und
     * Sätze numerisch, ein Money/Percentage-Objekt fiele sonst durch is_numeric().
     */
    private function scalar(mixed $value): mixed {
        return match (true) {
            $value instanceof Money => $value->getAmount(),
            $value instanceof Percentage => $value->getNumericValue(),
            $value instanceof Decimal => $value->getValue(),
            default => $value,
        };
    }

    /** Regulärer Ausdruck ohne Begrenzer, Groß-/Kleinschreibung egal; ein ungültiges Muster trifft nie. */
    private function matchesPattern(string $actual, string $pattern): bool {
        if ($pattern === '') {
            return false;
        }
        // Ein ungültiges Muster meldet preg_match als Warnung; die darf weder den
        // Lauf noch einen Test stören — der Handler schluckt sie, das Ergebnis ist „kein Treffer“.
        set_error_handler(static fn (): bool => true);
        try {
            return preg_match('~' . str_replace('~', '\\~', $pattern) . '~iu', $actual) === 1;
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    private function loose(mixed $value): mixed {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }
}
