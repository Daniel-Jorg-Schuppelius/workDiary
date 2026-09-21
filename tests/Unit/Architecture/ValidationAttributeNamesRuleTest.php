<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValidationAttributeNamesRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use Illuminate\Support\Str;
use PhpParser\{Node, NodeFinder, ParserFactory};
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate (MVP-826): Jedes validierte Feld hat in allen fünf Sprachen
 * einen Anzeigenamen unter `validation.attributes` — sonst lautete die Meldung
 * „Das Feld starts at muss …“. Erfasst werden rules()-Rückgaben (auch
 * `$rules['x'] = …`) sowie `validate([...])`, `validateWithBag(…, [...])` und
 * `Validator::make(…, [...])` in app/.
 */
class ValidationAttributeNamesRuleTest extends TestCase {
    use ScansSourceTree;

    private const LOCALES = ['de', 'en', 'fr', 'es', 'it'];

    /** Regelstrings, an denen ein Array als Regelsatz erkennbar ist. */
    private const RULE_HINT = '/^(bail|nullable|required|sometimes|present|filled|missing|prohibited|exclude|string|integer|numeric|boolean|date|array|list|json|file|image|email|url|uuid|ulid|ip|mac|timezone|in:|not_in:|max:|min:|size:|between:|digits|decimal|regex:|not_regex:|mimes|mimetypes|extensions|dimensions|alpha|ascii|lowercase|uppercase|starts_with|ends_with|distinct|accepted|declined|confirmed|current_password|active_url|date_format|after|before|gt:|gte:|lt:|lte:|same:|different:|exists:|unique:|multiple_of|hex_color|enum)/';

    public function test_every_validated_field_has_a_display_name_in_all_locales(): void {
        $attributes = [];
        foreach (self::LOCALES as $locale) {
            $attributes[$locale] = (array) ((require $this->repoRoot() . "/lang/{$locale}/validation.php")['attributes'] ?? []);
        }

        $missing = [];
        foreach ($this->validatedKeys() as $key => $where) {
            foreach (self::LOCALES as $locale) {
                if (! $this->hasName($attributes[$locale], $key)) {
                    $missing[] = sprintf('%s [%s] (%s)', $key, $locale, $where);
                }
            }
        }

        sort($missing);
        $this->assertSame([], $missing, "Validiertes Feld ohne Anzeigenamen — in lang/*/validation.php unter 'attributes' ergänzen (alle fünf Sprachen):\n" . implode("\n", $missing));
    }

    public function test_locales_name_the_same_fields(): void {
        $keys = [];
        foreach (self::LOCALES as $locale) {
            $keys[$locale] = array_keys((array) ((require $this->repoRoot() . "/lang/{$locale}/validation.php")['attributes'] ?? []));
            sort($keys[$locale]);
        }

        foreach (['en', 'fr', 'es', 'it'] as $locale) {
            $this->assertSame([], array_values(array_diff($keys['de'], $keys[$locale])), "Anzeigenamen fehlen in {$locale}");
            $this->assertSame([], array_values(array_diff($keys[$locale], $keys['de'])), "Anzeigenamen nur in {$locale}");
        }
    }

    /** Anzeigenamen sind Text: kein unaufgelöster Übersetzungsschlüssel, kein Platzhalter. */
    public function test_display_names_are_resolved_text(): void {
        $broken = [];
        foreach (self::LOCALES as $locale) {
            foreach ((array) ((require $this->repoRoot() . "/lang/{$locale}/validation.php")['attributes'] ?? []) as $key => $name) {
                if (preg_match('~values\.|\{\$|^[a-z_]+\.[a-z_.]+$|(?:^|\s):[a-z_]+~u', (string) $name) === 1) {
                    $broken[] = "{$locale}: {$key} = {$name}";
                }
            }
        }

        $this->assertSame([], $broken, "Anzeigename ist kein Text:\n" . implode("\n", $broken));
    }

    /** @param array<string, mixed> $names */
    private function hasName(array $names, string $key): bool {
        if (isset($names[$key]) && trim((string) $names[$key]) !== '') {
            return true;
        }
        foreach (array_keys($names) as $pattern) {
            if (str_contains((string) $pattern, '*') && Str::is((string) $pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> Schlüssel → erste Fundstelle */
    private function validatedKeys(): array {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $keys = [];

        foreach ($this->phpFiles('app') as $file) {
            $ast = $parser->parse((string) file_get_contents($file)) ?? [];
            $relative = $this->relativePath($file);
            $arrays = [];

            foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class) as $method) {
                $name = $method->name->toString();
                if ($name !== 'rules' && ! str_ends_with($name, 'Rules')) {
                    continue;
                }
                array_push($arrays, ...$finder->findInstanceOf($method->stmts ?? [], Node\Expr\Array_::class));
                foreach ($finder->findInstanceOf($method->stmts ?? [], Node\Expr\Assign::class) as $assign) {
                    if ($assign->var instanceof Node\Expr\ArrayDimFetch && $assign->var->dim instanceof Node\Scalar\String_) {
                        $keys[$assign->var->dim->value] ??= $relative;
                    }
                }
            }

            foreach ($finder->find($ast, static fn(Node $n): bool => $n instanceof Node\Expr\MethodCall || $n instanceof Node\Expr\StaticCall) as $call) {
                /** @var Node\Expr\MethodCall|Node\Expr\StaticCall $call */
                $arg = $this->rulesArgument($call);
                if ($arg !== null) {
                    $arrays[] = $arg;
                }
            }

            foreach ($arrays as $array) {
                foreach ($array->items as $item) {
                    if ($item->key instanceof Node\Scalar\String_ && $this->looksLikeRule($item->value)) {
                        $keys[$item->key->value] ??= $relative;
                    }
                }
            }
        }

        ksort($keys);

        return $keys;
    }

    private function rulesArgument(Node\Expr\MethodCall|Node\Expr\StaticCall $call): ?Node\Expr\Array_ {
        $name = $call->name instanceof Node\Identifier ? $call->name->toString() : '';
        $index = match (true) {
            $name === 'validate' => isset($call->args[1]) && ! ($call->args[0]->value ?? null) instanceof Node\Expr\Array_ ? 1 : 0,
            $name === 'validateWithBag' => 1,
            $name === 'make' && $call instanceof Node\Expr\StaticCall && $call->class instanceof Node\Name && str_ends_with($call->class->toString(), 'Validator') => 1,
            default => null,
        };
        $arg = $index !== null ? ($call->args[$index]->value ?? null) : null;

        return $arg instanceof Node\Expr\Array_ ? $arg : null;
    }

    private function looksLikeRule(Node\Expr $value): bool {
        if ($value instanceof Node\Scalar\String_) {
            return preg_match(self::RULE_HINT, $value->value) === 1;
        }

        return $value instanceof Node\Expr\Array_ || $value instanceof Node\Expr\New_ || $value instanceof Node\Expr\StaticCall
            || $value instanceof Node\Expr\Ternary || $value instanceof Node\Expr\FuncCall || $value instanceof Node\Expr\BinaryOp\Concat;
    }
}
