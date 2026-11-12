<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WriteRouteEntryRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Routing\Route;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\{MeasuresEffect, ScansSourceTree};

/**
 * Gate MVP-1620 „Schreibende Route ohne Einstieg“: Jede POST-, PUT-, PATCH-
 * oder DELETE-Route mit Namen wird von einer View, einem Skript oder einem
 * `route()`-Aufruf im PHP erreicht — als voller Name, über eine
 * Präfix-Variable (`route($routePrefix . '.store')`, `'admin.' . $id`), als
 * interpolierter Name (`"customer.{$area}.show"`) oder als Pfad im Skript
 * (`/chat/messages/${id}/react`). Maschinen-Endpunkte
 * ({@see MeasuresEffect::MACHINE_ROUTES}) zählen nicht.
 *
 * Eine Route ohne Einstieg ist eine Funktion, die niemand auslösen kann
 * (Scan 2026-11-10: 171 Kandidaten, drei echte).
 */
final class WriteRouteEntryRuleTest extends TestCase {
    use MeasuresEffect;
    use ScansSourceTree;

    private const string NAME = '[A-Za-z0-9_\-]+(?:\.[A-Za-z0-9_\-]+)*';

    public function test_every_write_route_has_an_entry(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/write-route-entries.php';
        $new = array_values(array_diff($this->offenders(), array_keys($baseline)));

        $this->assertSame([], $new, "Schreibende Route, die keine View, kein Skript und kein route()-Aufruf erreicht:\n  " . implode("\n  ", $new)
            . "\n\nEinstieg bauen (Knopf, Dialog, Aktion) oder die Route entfernen. Maschinen-Endpunkte gehören nach"
            . "\nMeasuresEffect::MACHINE_ROUTES, sonstige begründete Ausnahmen mit Grund nach baselines/write-route-entries.php.");
    }

    public function test_baseline_has_no_stale_entries(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/write-route-entries.php';
        $stale = array_values(array_diff(array_keys($baseline), $this->offenders()));

        $this->assertSame([], $stale, "Diese Routen haben inzwischen einen Einstieg oder gibt es nicht mehr — aus der Baseline streichen:\n  " . implode("\n  ", $stale));
        $this->assertSame([], $this->baselineEntriesWithoutReason($baseline), 'Baseline-Eintrag ohne konkreten Grund.');
    }

    /** Jedes Muster der Maschinen-Endpunkte trifft mindestens eine Route — sonst ist es eine vorweg erteilte Ausnahme. */
    public function test_every_machine_route_pattern_matches(): void {
        $unused = [];
        foreach (array_keys(self::MACHINE_ROUTES) as $pattern) {
            $hit = false;
            foreach ($this->writeRoutes() as $route) {
                if (preg_match($pattern, (string) $route->getName()) === 1) {
                    $hit = true;

                    break;
                }
            }
            if (! $hit) {
                $unused[] = $pattern;
            }
        }

        $this->assertSame([], $unused, 'Muster in MACHINE_ROUTES ohne Route — streichen.');
        $this->assertGreaterThan(1500, count($this->writeRoutes()));
    }

    /** @return list<string> Routenname */
    private function offenders(): array {
        $entries = $this->entries();
        $found = [];
        foreach ($this->writeRoutes() as $route) {
            $name = (string) $route->getName();
            if ($this->machineRouteReason($name, $route->uri()) === null && ! $this->isReached($name, $route->uri(), $entries)) {
                $found[] = $name;
            }
        }
        sort($found);

        return array_values(array_unique($found));
    }

    /** @return list<Route> benannte Routen mit schreibender Methode */
    private function writeRoutes(): array {
        $routes = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if ($route->getName() !== null && array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== []) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /** @param array{literals: array<string, true>, prefixes: array<string, true>, dynamicPrefixes: array<string, true>, suffixes: array<string, true>, patterns: array<string, true>, paths: array<string, true>} $entries */
    private function isReached(string $name, string $uri, array $entries): bool {
        if (isset($entries['literals'][$name])) {
            return true;
        }
        $parts = explode('.', $name);
        for ($i = 1; $i < count($parts); $i++) {
            $prefix = implode('.', array_slice($parts, 0, $i));
            if (! isset($entries['suffixes']['.' . implode('.', array_slice($parts, $i))])) {
                continue;
            }
            if (isset($entries['prefixes'][$prefix])) {
                return true;
            }
            foreach (array_keys($entries['dynamicPrefixes']) as $dynamic) {
                if (str_starts_with($prefix . '.', $dynamic) && ! str_contains(substr($prefix, strlen($dynamic)), '.')) {
                    return true;
                }
            }
        }
        foreach (array_keys($entries['patterns']) as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return true;
            }
        }
        $concrete = (string) preg_replace('~\{[^}]+\}~', 'X', $uri);
        foreach (array_keys($entries['paths']) as $pattern) {
            if (preg_match($pattern, $concrete) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return array{literals: array<string, true>, prefixes: array<string, true>, dynamicPrefixes: array<string, true>, suffixes: array<string, true>, patterns: array<string, true>, paths: array<string, true>} */
    private function entries(): array {
        $entries = ['literals' => [], 'prefixes' => [], 'dynamicPrefixes' => [], 'suffixes' => [], 'patterns' => [], 'paths' => []];
        $name = self::NAME;
        $corpus = $this->codeCorpus(['app', 'config', 'routes']);
        $corpus += $this->codeCorpus(['resources/views'], '/\.blade\.php$/');
        $corpus += $this->codeCorpus(['resources/js'], '/\.(?:js|ts|mjs)$/');
        foreach ($corpus as $relative => $source) {
            if (str_contains($relative, '/Database/Migrations/')) {
                continue;
            }
            if (str_starts_with($relative, 'routes/') || preg_match('~^app/Plugins/[^/]+/routes\.php$~', $relative) === 1) {
                // Definitionen sind kein Einstieg.
                $source = (string) preg_replace('~->name\(\s*[\'"][^\'"]*[\'"]\s*\)|[\'"]as[\'"]\s*=>\s*[\'"][^\'"]*[\'"]~', '', $source);
            }
            $isView = str_ends_with($relative, '.blade.php') || str_starts_with($relative, 'resources/js/');
            if ($isView && preg_match_all('~[\'"`](' . $name . ')[\'"`]~', $source, $m) > 0) {
                $entries['literals'] += array_fill_keys($m[1], true);
            }
            // PHP: Namen in Zuweisungen an $…route… und Array-Schlüsseln …route… (auch im Ternär).
            if (! $isView && preg_match_all('~(?:\$\w*[Rr]oute\w*\s*=(?!=)|[\'"]\w*route\w*[\'"]\s*=>)([^;\n]*)~', $source, $m) > 0) {
                foreach ($m[1] as $statement) {
                    if (preg_match_all('~[\'"](' . $name . ')[\'"]~', $statement, $names) > 0) {
                        $entries['literals'] += array_fill_keys($names[1], true);
                    }
                }
            }
            if (preg_match_all('~\b(?:route|Route|toList|signedRoute|temporarySignedRoute|to_route|routeIfExists)\s*\(~', $source, $calls, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($calls[0] as [$call, $offset]) {
                    $this->collectRouteArgument($this->firstArgument($this->balancedFrom($source, (int) $offset + strlen($call) - 1)), $entries);
                }
            }
            if (preg_match_all('~[\'"](' . $name . ')[\'"]~', $source, $m) > 0) {
                $entries['prefixes'] += array_fill_keys($m[1], true);
            }
            if (preg_match_all('~[\'"](' . $name . '\.)[\'"]\s*\.\s*\$~', $source, $m) > 0) {
                $entries['dynamicPrefixes'] += array_fill_keys($m[1], true);
            }
            if (preg_match_all('~"([A-Za-z0-9_.\-]*\{\$[^}"]+\}[^"\s]*)"~', $source, $m) > 0) {
                foreach ($m[1] as $interpolated) {
                    if (str_contains($interpolated, '.')) {
                        $entries['patterns'][$this->interpolationPattern($interpolated, '[A-Za-z0-9_\-]+')] = true;
                    }
                }
            }
            if ($isView && preg_match_all('~[\'"`](/[A-Za-z0-9_\-/${}.]+)[\'"`]~', $source, $m) > 0) {
                foreach ($m[1] as $path) {
                    $entries['paths'][$this->interpolationPattern(trim($path, '/'), '[^/]+')] = true;
                }
            }
            // Basis-URL fürs Skript (url('schedule/shifts')), das Skript hängt `/${id}/aktion` an.
            if ($isView && preg_match_all('~\burl\(\s*[\'"]([A-Za-z0-9_\-/]+)[\'"]\s*\)~', $source, $m) > 0) {
                foreach ($m[1] as $base) {
                    $entries['paths']['~^' . preg_quote(trim($base, '/'), '~') . '/X(?:/[A-Za-z0-9_\-]+)?$~'] = true;
                }
            }
        }

        return $entries;
    }

    /** Erstes Argument eines Aufrufs (Klammerinhalt bis zum ersten Komma auf oberster Ebene). */
    private function firstArgument(string $parenthesized): string {
        $inner = substr($parenthesized, 1, -1);
        $depth = 0;
        $quote = null;
        $length = strlen($inner);
        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif (in_array($char, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($char, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                return substr($inner, 0, $i);
            }
        }

        return $inner;
    }

    /**
     * Routenname aus dem ersten Argument von route(): Literale (auch im Ternär),
     * Suffixe hinter einer Präfix-Variablen und Verkettungen `'admin.' . $id . '.x'`.
     *
     * @param  array{literals: array<string, true>, prefixes: array<string, true>, dynamicPrefixes: array<string, true>, suffixes: array<string, true>, patterns: array<string, true>, paths: array<string, true>}  $entries
     */
    private function collectRouteArgument(string $argument, array &$entries): void {
        $argument = trim($argument);
        if (preg_match_all('~[\'"](\.?' . self::NAME . ')[\'"]~', $argument, $literals) === 0) {
            return;
        }
        foreach ($literals[1] as $literal) {
            if (str_starts_with($literal, '.')) {
                $entries['suffixes'][$literal] = true;
            } else {
                $entries['literals'][$literal] = true;
            }
        }
        // Verkettung mit führendem Literal: Variablen stehen für genau ein Namenssegment.
        if (preg_match('~^[\'"]~', $argument) === 1 && str_contains($argument, '$') && preg_match('~[?:]~', $argument) !== 1) {
            $pieces = preg_split('~\s*\.\s*(?=[\'"$])~', $argument) ?: [];
            $pattern = '';
            foreach ($pieces as $piece) {
                $pattern .= preg_match('~^[\'"](.*)[\'"]$~', $piece, $literal) === 1 ? preg_quote($literal[1], '~') : '[A-Za-z0-9_\-]+';
            }
            $entries['patterns']['~^' . $pattern . '$~'] = true;
        }
    }

    /** Interpolierten String als Regex: `{$x}`, `${x}` und `$x` werden Platzhalter. */
    private function interpolationPattern(string $value, string $placeholder): string {
        $parts = preg_split('/\{\$[^}]*\}|\$\{[^}]*\}|\$\w+(?:->\w+)*/', $value) ?: [$value];

        return '~^' . implode($placeholder, array_map(static fn (string $part): string => preg_quote($part, '~'), $parts)) . '$~';
    }
}
