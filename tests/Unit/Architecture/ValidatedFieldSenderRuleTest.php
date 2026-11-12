<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValidatedFieldSenderRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\{MeasuresEffect, ScansSourceTree};
use Throwable;

/**
 * Gate MVP-1620 „Validiertes Feld ohne Sender“: Jeder Regel-Schlüssel eines
 * Web-Requests (FormRequest `rules()` und `$request->validate([...])` in
 * Controllern außerhalb der API) wird von einer View oder einem Skript
 * gesendet — als `name`, als Namens-Prop einer Komponente (`from-name`,
 * `'newName' => …`), als Query-Parameter oder als Schlüssel, den ein Skript
 * in `FormData`/JSON schreibt. Der Scan vom 2026-11-10 fand so den
 * Abschlusscode am Ticket und Lager/Menge am Rückläufer: der Server
 * verlangt ein Feld, das kein Formular sendet.
 *
 * Gemessen auf Namensebene: `items.*.qty` gilt als gesendet, wenn irgendwo
 * `items[…][qty]` oder ein Feld `qty` steht. Schlüssel, die der Request in
 * `prepareForValidation()` selbst setzt (`merge`), zählen nicht.
 * Controller, die nur Maschinen-Endpunkte bedienen, sind ausgenommen.
 */
final class ValidatedFieldSenderRuleTest extends TestCase {
    use MeasuresEffect;
    use ScansSourceTree;

    /** Schreibweisen, mit denen Skripte Felder senden. */
    private const array SCRIPT_SENDERS = [
        '/\b(?:append|set)\(\s*[\'"`]([a-z_][A-Za-z0-9_\[\].]*)[\'"`]/',
        '/[{,]\s*([a-z_][A-Za-z0-9_]*)\s*:(?!:)/',
        '/[{,]\s*[\'"]([a-z_][A-Za-z0-9_]*)[\'"]\s*:/',
        '/[{,]\s*([a-z_][A-Za-z0-9_]*)(?=\s*[,}])/',
        '/\[name=[\'"]?([a-z_][a-z0-9_\[\].]*)/',
        '/\b(?:name|field|key|prefix)\s*[:=]\s*[\'"`]([a-z_][a-z0-9_\[\].]*)/',
        '/\|\|\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/',
    ];

    public function test_every_validated_web_field_has_a_sender(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/validated-field-senders.php';
        $new = array_values(array_diff($this->offenders(), array_keys($baseline)));

        $this->assertSame([], $new, "Validiertes Feld, das kein Formular und kein Skript sendet:\n  " . implode("\n  ", $new)
            . "\n\nFeld ins Formular aufnehmen oder die Regel streichen. Nur eine begründete Ausnahme gehört"
            . "\nmit Grund in baselines/validated-field-senders.php.");
    }

    public function test_baseline_has_no_stale_entries(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/validated-field-senders.php';
        $stale = array_values(array_diff(array_keys($baseline), $this->offenders()));

        $this->assertSame([], $stale, "Diese Felder haben inzwischen einen Sender oder gibt es nicht mehr — aus der Baseline streichen:\n  " . implode("\n  ", $stale));
        $this->assertSame([], $this->baselineEntriesWithoutReason($baseline), 'Baseline-Eintrag ohne konkreten Grund.');
    }

    public function test_the_scan_sees_rules_and_senders(): void {
        $this->assertGreaterThan(3000, count($this->senders()['names']));
        $this->assertGreaterThan(200, count($this->ruleSources()));
    }

    /** @return list<string> Datei::Feld */
    private function offenders(): array {
        $senders = $this->senders();
        $machine = $this->machineOnlyFiles();
        $found = [];
        foreach ($this->ruleSources() as $relative => $source) {
            if (isset($machine[$relative . '::*'])) {
                continue;
            }
            $merged = [];
            if (preg_match_all('/->(?:merge|mergeIfMissing|replace)\(\s*\[/', $source, $calls, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($calls[0] as [$call, $offset]) {
                    $merged += array_fill_keys($this->topLevelKeys($this->balancedFrom($source, (int) $offset + strlen($call) - 1)), true);
                }
            }
            foreach ($this->ruleArrays($relative, $source) as [$array, $method]) {
                if (isset($machine[$relative . '::' . $method])) {
                    continue;
                }
                foreach ($this->topLevelKeys($array) as $key) {
                    if (! isset($merged[$key]) && ! isset($merged[(string) preg_replace('/\.\*$/', '', $key)]) && ! $this->isSent($key, $senders)) {
                        $found[] = $relative . '::' . $key;
                    }
                }
            }
        }
        sort($found);

        return array_values(array_unique($found));
    }

    /** @return array<string, string> Web-Requests und Web-Controller mit Inline-Validierung */
    private function ruleSources(): array {
        $sources = [];
        foreach ($this->codeCorpus(['app/Http/Requests', 'app/Http/Controllers', 'app/Plugins']) as $relative => $source) {
            if (str_contains($relative, '/Api/')) {
                continue;
            }
            if (str_starts_with($relative, 'app/Plugins/') && ! str_ends_with($relative, 'Controller.php') && ! str_contains($relative, '/Requests/')) {
                continue;
            }
            $sources[$relative] = $source;
        }

        return $sources;
    }

    /** @return list<array{0: string, 1: string}> Regel-Arrays mit umgebender Methode: `rules()` eines Requests, `validate([...])` eines Controllers */
    private function ruleArrays(string $relative, string $source): array {
        $arrays = [];
        if (str_contains($relative, '/Requests/')) {
            if (preg_match('/function rules\(\)[^{]*\{/', $source, $start, PREG_OFFSET_CAPTURE) === 1) {
                $body = $this->balancedFrom($source, (int) $start[0][1] + strlen($start[0][0]) - 1);
                if (preg_match_all('/(?:return|=)\s*\[/', $body, $returns, PREG_OFFSET_CAPTURE) > 0) {
                    foreach ($returns[0] as [$token, $offset]) {
                        $arrays[] = [$this->balancedFrom($body, (int) $offset + strlen($token) - 1), 'rules'];
                    }
                }
            }

            return $arrays;
        }
        if (preg_match_all('/(?:->validate|Validator::make)\(\s*(?:\$request->(?:all|input|only)\([^)]*\)\s*,\s*)?\[/', $source, $calls, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($calls[0] as [$token, $offset]) {
                $method = preg_match_all('/function\s+(\w+)\s*\(/', substr($source, 0, (int) $offset), $functions) > 0 ? (string) end($functions[1]) : '';
                $arrays[] = [$this->balancedFrom($source, (int) $offset + strlen($token) - 1), $method];
            }
        }

        return $arrays;
    }

    /** @return list<string> String-Schlüssel der obersten Ebene eines Array-Literals */
    private function topLevelKeys(string $array): array {
        $keys = [];
        $depth = 0;
        $quote = null;
        $buffer = '';
        $length = strlen($array);
        for ($i = 0; $i < $length; $i++) {
            $char = $array[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\') {
                    $buffer .= $array[++$i] ?? '';
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $buffer .= $char;

                continue;
            }
            if (in_array($char, ['[', '(', '{'], true)) {
                $depth++;
                if ($depth === 1) {
                    $buffer = '';

                    continue;
                }
            } elseif (in_array($char, [']', ')', '}'], true)) {
                $depth--;
            }
            if ($depth === 1 && $char === ',') {
                $buffer = '';

                continue;
            }
            if ($depth === 1) {
                $buffer .= $char;
                if (preg_match('/^\s*[\'"]([^\'"]+)[\'"]\s*=>$/', $buffer, $key) === 1) {
                    $keys[] = $key[1];
                }
            }
        }

        return $keys;
    }

    /** @param array{names: array<string, true>, leaves: array<string, true>, wildcards: list<string>} $senders */
    private function isSent(string $key, array $senders): bool {
        $key = (string) preg_replace('/\.\*$/', '', $key);
        if (isset($senders['names'][$key])) {
            return true;
        }
        $concrete = str_replace('*', '0', $key);
        foreach ($senders['wildcards'] as $pattern) {
            if (preg_match($pattern, $concrete) === 1) {
                return true;
            }
        }
        $parts = array_values(array_filter(explode('.', $key), static fn (string $part): bool => $part !== '*'));

        return $parts !== [] && isset($senders['leaves'][end($parts)]);
    }

    /** @return array{names: array<string, true>, leaves: array<string, true>, wildcards: list<string>} */
    private function senders(): array {
        $names = [];
        $corpus = $this->codeCorpus(['resources/views', 'app/Plugins'], '/\.blade\.php$/');
        $corpus += $this->codeCorpus(['resources/js'], '/\.(?:js|ts|mjs)$/');
        foreach ($corpus as $relative => $source) {
            $isScript = ! str_ends_with($relative, '.blade.php');
            if (! $isScript) {
                $source = $this->stripBladeComments($source);
                foreach ([
                    '/(?<![\w-])(?:wire:model(?:\.\w+)*|[\w-]*(?:name|Name|field|Field|prefix|Prefix))\s*=\s*"([^"]+)"/',
                    '/:[\w-]*(?:name|Name|field|Field|prefix|Prefix)\s*=\s*"\'([^\']+)\'/',
                    '/[\'"]\w*(?:name|Name|field|Field|prefix|Prefix)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/',
                    '/\$\w*(?:name|Name)\s*=\s*(\'[^;]+);/',
                    '/[?&]([a-z_][a-z0-9_]*)=/',
                ] as $pattern) {
                    if (preg_match_all($pattern, $source, $m) > 0) {
                        foreach ($m[1] as $name) {
                            $names[$this->normalizeName($name)] = true;
                        }
                    }
                }
                // Feldnamen aus einer Klassenkonstante: @foreach (\X::CRITERIA as $criterion) … name="{{ $criterion }}".
                if (preg_match_all('/@foreach\s*\(\s*\\\\?([\w\\\\]+)::([A-Z_]+)\s+as\s+\$(\w+)\s*\)/', $source, $constants, PREG_SET_ORDER) > 0) {
                    foreach ($constants as [, $class, $constant, $var]) {
                        if (defined($class . '::' . $constant) && preg_match('/name\s*=\s*"[^"]*\{\{\s*\$' . $var . '\s*\}\}/', $source) === 1) {
                            $names += array_fill_keys(array_filter((array) constant($class . '::' . $constant), 'is_string'), true);
                        }
                    }
                }
                // Feldnamen aus einer Schleife: @foreach (['a_id' => 'a', …] as $field => $label) … name="{{ $field }}".
                if (preg_match_all('/@foreach\s*\(\s*\[(.*?)\]\s*as\s*\$(\w+)(?:\s*=>\s*\$(\w+))?\s*\)/s', $source, $loops, PREG_SET_ORDER) > 0) {
                    foreach ($loops as $loop) {
                        $hasKey = ($loop[3] ?? '') !== '';
                        $variables = [($hasKey ? $loop[3] : $loop[2]) => '/(?:^|,|\[)\s*[\'"]([a-z_][a-z0-9_]*)[\'"]\s*(?=,|$|\])/'];
                        if ($hasKey) {
                            $variables[$loop[2]] = '/[\'"]([a-z_][a-z0-9_]*)[\'"]\s*=>/';
                        }
                        foreach ($variables as $var => $pattern) {
                            if (preg_match('/name\s*=\s*"[^"]*(?:\{\{\s*\$' . $var . '\s*\}\}|\$' . $var . '\b)/', $source) === 1 && preg_match_all($pattern, $loop[1], $items) > 0) {
                                $names += array_fill_keys($items[1], true);
                            }
                        }
                    }
                }
                // Query-Parameter in route('…', ['status' => …]).
                if (preg_match_all('/route\(\s*[\'"][^\'"]+[\'"]\s*,\s*\[([^\]]*)\]/', $source, $m) > 0) {
                    foreach ($m[1] as $parameters) {
                        preg_match_all('/[\'"]([a-z_][a-z0-9_]*)[\'"]\s*=>/', $parameters, $keys);
                        $names += array_fill_keys($keys[1], true);
                    }
                }
            }
            $scripts = $isScript ? [$source] : (preg_match_all('~<script\b[^>]*>(.*?)</script>~s', $source, $s) > 0 ? $s[1] : []);
            if (! $isScript && preg_match_all('/\bx-data\s*=\s*"([^"]*)"/', $source, $x) > 0) {
                $scripts = [...$scripts, ...$x[1]];
            }
            foreach ($scripts as $script) {
                foreach (self::SCRIPT_SENDERS as $pattern) {
                    if (preg_match_all($pattern, $script, $m) > 0) {
                        foreach ($m[1] as $name) {
                            $names[$this->normalizeName($name)] = true;
                        }
                    }
                }
            }
        }
        $leaves = [];
        $wildcards = [];
        foreach (array_keys($names) as $name) {
            $parts = explode('.', (string) $name);
            foreach ($parts as $i => $part) {
                if ($part !== '*') {
                    $leaves[$part] = true;
                }
                if ($i > 0) {
                    $prefix = implode('.', array_slice($parts, 0, $i));
                    if (preg_match('/[a-z]/', $prefix) === 1) {
                        $names[$prefix] = true;
                    }
                }
            }
            if (str_contains((string) $name, '*') && preg_match('/[a-z]/', (string) $name) === 1) {
                $wildcards[] = '/^' . str_replace('\*', '[^.]+', preg_quote((string) $name, '/')) . '(?:\..*)?$/';
            }
        }

        return ['names' => $names, 'leaves' => $leaves, 'wildcards' => $wildcards];
    }

    /** `items[{{ $i }}][qty]` → `items.*.qty`; dynamische Teile werden zu `*`. */
    private function normalizeName(string $name): string {
        $name = (string) preg_replace('/\{\{.*?\}\}/', '*', $name);
        // PHP- oder Alpine-Ausdruck (`'items[' . $i . '][qty]'`, `fieldName(i, 'qty')`): nur die Literale zählen;
        // eine Variable vor oder nach den Literalen (`$anchor . '_address'`) bleibt als `*` stehen.
        if (preg_match_all("/'([^']*)'/", $name, $literals) > 0) {
            $edges = preg_split("/'[^']*'/", $name) ?: [];
            $variable = static fn (string $edge): string => preg_match('/^\s*\.?\s*\$\w+(?:->\w+)*\s*\.?\s*$/', $edge) === 1 ? '*' : '';
            $name = $variable((string) ($edges[0] ?? '')) . implode('*', $literals[1]) . $variable((string) end($edges));
        }
        $name = (string) preg_replace('/\$\{[^}]*\}|\{\$[^}]*\}|\$\w+/', '*', $name);
        $name = str_replace(['[]', '][', '[', ']'], ['.*', '.', '.', ''], $name);

        return trim($name, '.');
    }

    /** @return array<string, true> `Datei::Methode` bzw. `Request-Datei::*`, die nur Maschinen-Endpunkte bedienen */
    private function machineOnlyFiles(): array {
        $byFile = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action, 2);
            $machine = $this->machineRouteReason((string) $route->getName(), $route->uri()) !== null;
            try {
                $reflection = new ReflectionMethod($class, $method);
            } catch (Throwable) {
                continue;
            }
            $files = [$this->relativePath((string) $reflection->getFileName()) . '::' . $method];
            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                    $files[] = $this->relativePath((string) (new \ReflectionClass($type->getName()))->getFileName()) . '::*';
                }
            }
            foreach ($files as $file) {
                $byFile[$file] = ($byFile[$file] ?? true) && $machine;
            }
        }

        return array_filter($byFile);
    }
}
