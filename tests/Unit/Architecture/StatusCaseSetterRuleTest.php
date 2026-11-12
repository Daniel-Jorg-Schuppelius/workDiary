<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatusCaseSetterRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Enums\Contracts\HasStatusTransitions;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\{MeasuresEffect, ScansSourceTree};
use Throwable;

/**
 * Gate MVP-1620 „Statusfall ohne Setzer“: Jeder Fall eines Status-Enums
 * (`HasStatusTransitions` oder Name `*Status`) wird gesetzt — von Code, der
 * ihn namentlich schreibt, vom Spalten-Standardwert oder von einem Formular,
 * dessen Validierung den Fall zulässt (`Rule::enum`, `new Enum`, auch mit
 * `only()`/`except()`, `Rule::in(X::values())`). Ein Fall, den nur Filter,
 * Kacheln und Vergleiche kennen, tritt nie ein (Scan 2026-11-10: Bürgschaft
 * „Rückgabe fällig“, Einbehalt „freigegeben“).
 *
 * Lesend zählt ein Vorkommen nach `===`, `!==`, `==`, `!=`, `case`, als
 * Match-Arm, in `where…()`/`whereIn()`/`in_array()` und mit Lesemethode
 * (`->label()`, `->isX()`). Alles andere gilt als Setzer — die Messung irrt
 * damit zugunsten des Bestands.
 */
final class StatusCaseSetterRuleTest extends TestCase {
    use MeasuresEffect;
    use ScansSourceTree;

    /** Nach dem Fall: lesender Gebrauch. */
    private const string READ_AFTER = '/^\s*(?:===|!==|==(?!=)|!=(?!=)|=>|->\s*(?:label|color|colour|icon|badge\w*|tone|description|equals|allowedTransitions|canTransitionTo|is[A-Z]?\w*)\s*\(|->\s*value\s*(?:===|!==|==|!=))/';

    /** Vor dem Fall: Vergleich, `case` oder Filterargument. */
    private const string READ_BEFORE = '/(?:===|!==|==|!=|\bcase|->(?:where|orWhere|whereNot|orWhereNot|having|firstWhere|contains|doesntContain|reject)\w*\(\s*(?:[\'"][\w.]+[\'"]|\$\w+)\s*,\s*(?:[\'"](?:!=|<>|=)[\'"]\s*,\s*)?)\s*$/';

    /** Aufrufe, deren Array-Argument eine Lese- oder Filterliste ist. */
    private const string READ_LIST = '/(?:whereIn|whereNotIn|orWhereIn|orWhereNotIn|in_array|->in|->contains|->only|->except|array_intersect|array_diff)\s*\([^()]*$/';

    /** Methoden, deren Validierung ein Filter ist, kein Setzer. */
    private const string FILTER_METHODS = '/^(?:index|export\w*|feed|filter\w*|report\w*|show|chart\w*|data|search|summary|list\w*)$/i';

    /** @var array<string, true> Enums mit Ziel aus der Eingabe (`X::tryFrom($data[…])`) */
    private array $dynamicTargets = [];

    public function test_every_status_case_has_a_setter(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/status-case-setters.php';
        $new = array_values(array_diff($this->offenders(), array_keys($baseline)));

        $this->assertSame([], $new, "Statusfall, den weder Code noch ein Formular noch der Spalten-Standard setzt:\n  " . implode("\n  ", $new)
            . "\n\nSetzer bauen (Dienst, Übergangsdialog) oder den Fall entfernen — gespeicherte Werte vorher per Migration umschreiben."
            . "\nNur eine begründete Ausnahme gehört mit Grund in baselines/status-case-setters.php.");
    }

    public function test_baseline_has_no_stale_entries(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/status-case-setters.php';
        $stale = array_values(array_diff(array_keys($baseline), $this->offenders()));

        $this->assertSame([], $stale, "Diese Fälle haben inzwischen einen Setzer oder gibt es nicht mehr — aus der Baseline streichen:\n  " . implode("\n  ", $stale));
        $this->assertSame([], $this->baselineEntriesWithoutReason($baseline), 'Baseline-Eintrag ohne konkreten Grund.');
    }

    public function test_the_scan_sees_status_enums(): void {
        $this->assertGreaterThan(250, count($this->statusEnums()));
    }

    /** @return list<string> Enum::Fall */
    private function offenders(): array {
        $enums = $this->statusEnums();
        $set = $this->setters($enums);
        foreach ($this->columnDefaults($enums) as $fqcn => $cases) {
            foreach ($cases as $case) {
                $set[$fqcn][$case] = true;
            }
        }
        // Generischer Übergang: Ziel aus der Eingabe (X::tryFrom($data['status'])), geprüft gegen
        // allowedTransitions() — erreichbar ist, was von einem gesetzten Fall aus erlaubt ist.
        foreach (array_keys($this->dynamicTargets) as $fqcn) {
            if (! is_subclass_of($fqcn, HasStatusTransitions::class)) {
                foreach (array_keys($enums[$fqcn]['cases']) as $case) {
                    $set[$fqcn][$case] = true;
                }

                continue;
            }
            $queue = array_keys($set[$fqcn] ?? []);
            while ($queue !== []) {
                $from = constant($fqcn . '::' . array_shift($queue));
                foreach ($from->allowedTransitions() as $to) {
                    if (! isset($set[$fqcn][$to->name])) {
                        $set[$fqcn][$to->name] = true;
                        $queue[] = $to->name;
                    }
                }
            }
        }
        $found = [];
        foreach ($enums as $fqcn => $enum) {
            foreach (array_keys($enum['cases']) as $case) {
                if (! isset($set[$fqcn][$case])) {
                    $found[] = $fqcn . '::' . $case;
                }
            }
        }
        sort($found);

        return $found;
    }

    /** @return array<string, array{file: string, cases: array<string, string|null>}> FQCN → Datei und Fälle (Name → Wert) */
    private function statusEnums(): array {
        $enums = [];
        foreach ([...$this->phpFiles('app/Enums'), ...$this->phpFiles('app/Plugins')] as $file) {
            $relative = $this->relativePath($file);
            if (str_starts_with($relative, 'app/Plugins/') && ! str_contains($relative, '/Enums/')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            if (preg_match('/^enum\s+(\w+)/m', $source, $enum) !== 1 || preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1) {
                continue;
            }
            if (! str_ends_with($enum[1], 'Status') && ! str_contains($source, 'HasStatusTransitions')) {
                continue;
            }
            preg_match_all('/^\s*case\s+(\w+)\s*(?:=\s*([\'"])(.*?)\2)?\s*;/m', $source, $cases, PREG_SET_ORDER);
            $values = [];
            foreach ($cases as $case) {
                $values[$case[1]] = $case[3] ?? null;
            }
            $enums[$namespace[1] . '\\' . $enum[1]] = ['file' => $relative, 'cases' => $values];
        }
        ksort($enums);

        return $enums;
    }

    /**
     * @param  array<string, array{file: string, cases: array<string, string|null>}>  $enums
     * @return array<string, array<string, true>> FQCN → gesetzte Fälle
     */
    private function setters(array $enums): array {
        $set = [];
        $this->dynamicTargets = [];
        $providers = [];
        $dynamicCases = [];
        $corpus = $this->codeCorpus(['app', 'routes', 'config', 'database/seeders']);
        $corpus += $this->codeCorpus(['resources/views'], '/\.blade\.php$/');
        foreach ($corpus as $relative => $source) {
            $aliases = $this->imports($source);
            $namespace = preg_match('/^namespace\s+([^;]+);/m', $source, $ns) === 1 ? $ns[1] : '';
            $resolve = static fn (string $ref): string => str_contains($ref, '\\') ? ltrim($ref, '\\') : ($aliases[$ref] ?? $namespace . '\\' . $ref);

            foreach ($this->validatedCases($source, $resolve, $enums) as $fqcn => $cases) {
                foreach ($cases as $case) {
                    $set[$fqcn][$case] = true;
                }
            }
            // X::from($eingabe) / X::tryFrom($eingabe) außerhalb von Filtern: Ziel kommt aus Formular oder Fremdsystem.
            if (preg_match_all('/(\\\\?(?:[A-Z]\w*\\\\)*[A-Z]\w*)::(?:from|tryFrom)\(\s*(?:\(\w+\)\s*)?(\$[^)]*)\)/', $source, $froms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
                foreach ($froms as $from) {
                    $fqcn = $resolve($from[1][0]);
                    $argument = $from[2][0];
                    $method = preg_match_all('/function\s+(\w+)\s*\(/', substr($source, 0, (int) $from[0][1]), $functions) > 0 ? (string) end($functions[1]) : '';
                    if (isset($enums[$fqcn]) && preg_match(self::FILTER_METHODS, $method) !== 1 && preg_match('/^\$\w+(?:\?->|->)\w*status\b/i', $argument) !== 1) {
                        $this->dynamicTargets[$fqcn] = true;
                    }
                }
            }
            // Generische Bausteine: Methode `…Enum(): string { return X::class; }` und Zugriff `$status::Fall`.
            if (preg_match_all('/function\s+\w*Enum\w*\(\)\s*:\s*string\s*\{\s*return\s+(\\\\?[\w\\\\]+)::class;/', $source, $provided) > 0) {
                foreach ($provided[1] as $ref) {
                    $providers[$resolve($ref)] = true;
                }
            }
            if (preg_match_all('/\$\w+::([A-Z]\w*)\b(?!\s*\()/', $source, $dynamic, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
                foreach ($dynamic as $hit) {
                    if (! $this->isRead($source, (int) $hit[0][1], strlen($hit[0][0]))) {
                        $dynamicCases[$hit[1][0]] = true;
                    }
                }
            }
            if (preg_match_all('/(\\\\?(?:[A-Z]\w*\\\\)*[A-Z]\w*)::([A-Z]\w*)\b(?!\s*\()/', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
                continue;
            }
            foreach ($matches as $match) {
                $fqcn = $resolve($match[1][0]);
                $case = $match[2][0];
                if (! isset($enums[$fqcn]['cases']) || ! array_key_exists($case, $enums[$fqcn]['cases']) || $enums[$fqcn]['file'] === $relative) {
                    continue;
                }
                $offset = (int) $match[0][1];
                if (! $this->isRead($source, $offset, strlen($match[0][0]))) {
                    $set[$fqcn][$case] = true;
                }
            }
        }
        foreach (array_keys($providers) as $fqcn) {
            foreach (array_keys($enums[$fqcn]['cases'] ?? []) as $case) {
                if (isset($dynamicCases[$case])) {
                    $set[$fqcn][$case] = true;
                }
            }
        }
        // Fabrikmethoden des Enums (fromSdk(), fromRemote()): ihre Fälle setzt, wer die Methode aufruft.
        foreach ($enums as $fqcn => $enum) {
            $short = substr((string) strrchr('\\' . $fqcn, '\\'), 1);
            foreach ($this->factoryCases((string) ($corpus[$enum['file']] ?? '')) as $method => $cases) {
                foreach ($corpus as $relative => $source) {
                    if ($relative !== $enum['file'] && preg_match('/\b' . $short . '::' . $method . '\(/', $source) === 1) {
                        foreach ($cases === ['*'] ? array_keys($enum['cases']) : $cases as $case) {
                            $set[$fqcn][$case] = true;
                        }

                        break;
                    }
                }
            }
        }

        return $set;
    }

    /** @return array<string, list<string>> statische Methode → Fälle, die sie zurückgeben kann */
    private function factoryCases(string $enumSource): array {
        $out = [];
        if (preg_match_all('/public\s+static\s+function\s+(\w+)\s*\([^)]*\)\s*:\s*\??(?:self|static)\s*\{/', $enumSource, $methods, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return $out;
        }
        foreach ($methods as $method) {
            $body = $this->balancedFrom($enumSource, (int) $method[0][1] + strlen($method[0][0]) - 1);
            if (preg_match('/\b(?:self|static)::(?:tryFrom|from)\(/', $body) === 1) {
                $out[$method[1][0]] = ['*'];
            } elseif (preg_match_all('/\bself::([A-Z]\w*)\b(?!\s*\()/', $body, $cases) > 0) {
                $out[$method[1][0]] = array_values(array_unique($cases[1]));
            }
        }

        return $out;
    }

    private function isRead(string $source, int $offset, int $length): bool {
        $after = substr($source, $offset + $length, 160);
        $before = substr($source, max(0, $offset - 200), min(200, $offset));
        if (preg_match(self::READ_AFTER, $after) === 1 || preg_match(self::READ_BEFORE, $before) === 1) {
            return true;
        }
        $open = strrpos($before, '[');
        $close = strrpos($before, ']');
        if ($open !== false && ($close === false || $close < $open) && preg_match(self::READ_LIST, substr($before, 0, $open)) === 1) {
            return true;
        }

        // Liste eines Match-Arms: X::A, X::B => … — nicht der Wert eines Arms (Y::B => X::A, Y::C => …).
        return preg_match('/=>\s*$/', $before) !== 1
            && preg_match('/^\s*,\s*(?:\\\\?[\w\\\\]+::\w+\s*,\s*)*\\\\?[\w\\\\]+::\w+\s*,?\s*=>/', $after) === 1;
    }

    /**
     * Fälle, die eine Formularvalidierung zulässt — außerhalb von Filter-Methoden.
     *
     * @param  callable(string): string  $resolve
     * @param  array<string, array{file: string, cases: array<string, string|null>}>  $enums
     * @return array<string, list<string>>
     */
    private function validatedCases(string $source, callable $resolve, array $enums): array {
        $pattern = '/(?:Rule::enum|new\s+(?:\\\\?Illuminate\\\\Validation\\\\Rules\\\\)?Enum)\(\s*(\\\\?[\w\\\\]+)::class\s*\)(?:\s*->\s*(only|except)\(((?:[^()]|\((?3)\))*)\))?|Rule::in\(\s*(\\\\?[\w\\\\]+)::values\(\)\s*\)/';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }
        $out = [];
        foreach ($matches as $match) {
            $fqcn = $resolve(($match[1][0] ?? '') !== '' ? $match[1][0] : (string) ($match[4][0] ?? ''));
            if (! isset($enums[$fqcn])) {
                continue;
            }
            $method = preg_match_all('/function\s+(\w+)\s*\(/', substr($source, 0, (int) $match[0][1]), $functions) > 0 ? (string) end($functions[1]) : '';
            if (preg_match(self::FILTER_METHODS, $method) === 1) {
                continue;
            }
            $mode = $match[2][0] ?? '';
            $listed = $mode === '' ? [] : $this->listedCases($fqcn, (string) ($match[3][0] ?? ''));
            foreach (array_keys($enums[$fqcn]['cases']) as $case) {
                if (($mode === 'only' && ! in_array($case, $listed, true)) || ($mode === 'except' && in_array($case, $listed, true))) {
                    continue;
                }
                $out[$fqcn][] = $case;
            }
        }

        return $out;
    }

    /** @return list<string> Fälle aus `[X::A, X::B]` oder einer statischen Methode `X::gruppe()` */
    private function listedCases(string $fqcn, string $argument): array {
        if (preg_match('/::(\w+)\(\)/', $argument, $call) === 1 && method_exists($fqcn, $call[1])) {
            try {
                $cases = $fqcn::{$call[1]}();

                return array_values(array_map(static fn ($case): string => $case->name, is_array($cases) ? $cases : []));
            } catch (Throwable) {
                return [];
            }
        }
        preg_match_all('/::([A-Z]\w*)\b(?!\s*\()/', $argument, $cases);

        return $cases[1];
    }

    /**
     * Fälle, die als Spalten-Standardwert gesetzt werden: Modell castet die
     * Spalte auf das Enum, der Schema-Dump trägt `DEFAULT '<wert>'`.
     *
     * @param  array<string, array{file: string, cases: array<string, string|null>}>  $enums
     * @return array<string, list<string>>
     */
    private function columnDefaults(array $enums): array {
        $tables = $this->schemaTables();
        $out = [];
        foreach ($this->modelFiles() as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/^namespace\s+([^;]+);/m', $source, $ns) !== 1 || preg_match('/^(?:final |abstract )*class\s+(\w+)/m', $source, $class) !== 1) {
                continue;
            }
            $aliases = $this->imports($source);
            $table = preg_match('/\$table\s*=\s*[\'"](\w+)[\'"]/', $source, $t) === 1 ? $t[1] : Str::snake(Str::pluralStudly($class[1]));
            if (preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*(\\\\?[\w\\\\]+)::class/', $source, $casts, PREG_SET_ORDER) === 0) {
                continue;
            }
            foreach ($casts as [, $column, $ref]) {
                $fqcn = str_contains($ref, '\\') ? ltrim($ref, '\\') : ($aliases[$ref] ?? $ns[1] . '\\' . $ref);
                $definition = $tables[$table]['columns'][$column] ?? '';
                if (! isset($enums[$fqcn]) || preg_match("/DEFAULT '([^']*)'/", $definition, $default) !== 1) {
                    continue;
                }
                $case = array_search($default[1], $enums[$fqcn]['cases'], true);
                if (is_string($case)) {
                    $out[$fqcn][] = $case;
                }
            }
        }

        return $out;
    }

    /** @return array<string, string> Kurzname/Alias → FQCN aus den `use`-Zeilen */
    private function imports(string $source): array {
        $aliases = [];
        if (preg_match_all('/^use\s+([^;]+);/m', $source, $uses) === 0) {
            return $aliases;
        }
        foreach ($uses[1] as $use) {
            $groups = preg_match('/^(.*)\\\\\{(.*)\}$/s', trim($use), $group) === 1
                ? array_map(static fn (string $part): string => trim($group[1], '\\') . '\\' . trim($part), array_filter(array_map('trim', explode(',', $group[2]))))
                : [trim($use)];
            foreach ($groups as $entry) {
                [$class, $alias] = array_pad(preg_split('/\s+as\s+/i', ltrim($entry, '\\')) ?: [], 2, null);
                if (is_string($class) && $class !== '') {
                    $aliases[$alias ?? substr((string) strrchr('\\' . $class, '\\'), 1)] = $class;
                }
            }
        }

        return $aliases;
    }

    /** Die Status-Enums mit Übergangstabelle sind Teil der Messung — auch ohne Endung `Status`. */
    public function test_transition_enums_are_measured(): void {
        $measured = array_keys($this->statusEnums());
        foreach ($measured as $fqcn) {
            if (is_subclass_of($fqcn, HasStatusTransitions::class)) {
                $this->addToAssertionCount(1);

                return;
            }
        }
        $this->fail('Keine Übergangs-Enums erkannt.');
    }
}
