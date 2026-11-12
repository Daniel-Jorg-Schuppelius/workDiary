<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceMethodCallerRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Unit\Architecture\Concerns\{MeasuresEffect, ScansSourceTree};
use Throwable;

/**
 * Gate MVP-1620 „Dienstmethode ohne Aufrufer“: Eine öffentliche Methode in
 * `app/Services`, `app/Plugins/<Name>/Services` oder `app/Plugins/Support`
 * hat eine Aufrufstelle außerhalb von Tests und Demo-Blöcken. Der Scan vom
 * 2026-11-10 fand so den einzigen Schreiber der Gleitzeitsalden
 * (`FlexCalculator::recompute()`) und den Backup-Nachweis ohne Aufrufer.
 *
 * Als Aufruf zählt der Name nach `->`, `?->` oder `::` (auch über Verträge,
 * Blade, Routen, Konfiguration) und jedes String-Literal mit dem Namen
 * (Callables, `app()->call`, Listener-Zuordnung). Methoden, die ein
 * Framework- oder Paket-Typ der Klasse deklariert (Interface, Elternklasse),
 * ruft das Framework; Trait-Initialisierer und Relationen gelten als Aufruf,
 * wenn ihr Name als Eigenschaft gelesen wird.
 */
final class ServiceMethodCallerRuleTest extends TestCase {
    use MeasuresEffect;
    use ScansSourceTree;

    /** Methodennamen, die Laravel selbst aufruft. */
    private const array FRAMEWORK_METHODS = [
        'handle', 'boot', 'register', 'booted', 'failed', 'middleware', 'via', 'subscribe', 'retryUntil', 'backoff',
        'tags', 'uniqueId', 'uniqueFor', 'displayName', 'broadcastOn', 'toArray', 'jsonSerialize', 'toResponse', 'toHtml',
    ];

    private const string RELATION = '/\$this->(?:belongsTo|hasMany|hasOne|morphTo|morphMany|morphOne|belongsToMany|hasManyThrough|hasOneThrough|morphToMany|morphedByMany)\(/';

    /** @var array{calls: array<string, true>, strings: array<string, true>, properties: array<string, true>}|null */
    private static ?array $index = null;

    public function test_public_service_methods_have_a_caller(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/service-method-callers.php';
        $new = array_values(array_diff($this->offenders(), array_keys($baseline)));

        $this->assertSame([], $new, "Öffentliche Dienstmethode ohne Aufrufer außerhalb von Tests und Demo-Blöcken:\n  " . implode("\n  ", $new)
            . "\n\nAnschließen (Befehl, Job, Listener, Controller) oder entfernen. Nur eine begründete Ausnahme"
            . "\n(Aufruf, den die Messung nicht sieht) gehört mit Grund in baselines/service-method-callers.php.");
    }

    public function test_baseline_has_no_stale_entries(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/service-method-callers.php';
        $stale = array_values(array_diff(array_keys($baseline), $this->offenders()));

        $this->assertSame([], $stale, "Diese Methoden haben inzwischen einen Aufrufer oder gibt es nicht mehr — aus der Baseline streichen:\n  " . implode("\n  ", $stale));
        $this->assertSame([], $this->baselineEntriesWithoutReason($baseline), 'Baseline-Eintrag ohne konkreten Grund.');
    }

    public function test_the_scan_sees_services_and_callers(): void {
        $this->assertGreaterThan(2000, count($this->scopeFiles()));
        $this->assertGreaterThan(10000, count($this->index()['calls']));
    }

    /** @return list<string> Datei::Methode */
    private function offenders(): array {
        $index = $this->index();
        $found = [];
        foreach ($this->scopeFiles() as $relative) {
            $source = (string) file_get_contents($this->repoRoot() . '/' . $relative);
            foreach ($this->publicMethods($source) as $method) {
                if ($this->isCalled($method, $source, $index)) {
                    continue;
                }
                if ($this->declaredByFramework($source, $method)) {
                    continue;
                }
                $found[] = $relative . '::' . $method;
            }
        }
        sort($found);

        return array_values(array_unique($found));
    }

    /** @param array{calls: array<string, true>, strings: array<string, true>, properties: array<string, true>} $index */
    private function isCalled(string $method, string $source, array $index): bool {
        if (str_starts_with($method, '__') || in_array($method, self::FRAMEWORK_METHODS, true)) {
            return true;
        }
        if (isset($index['calls'][$method]) || isset($index['strings'][$method])) {
            return true;
        }
        // Eloquent-Trait-Initialisierer (initializeX/bootX) ruft das Modell beim Instanziieren.
        if (preg_match('/^(?:initialize|boot)[A-Z]/', $method) === 1) {
            return true;
        }
        // Scopes und Accessoren in Modell-Traits.
        if (preg_match('/^scope([A-Z]\w*)$/', $method, $scope) === 1 && isset($index['calls'][lcfirst($scope[1])])) {
            return true;
        }
        // Relationen werden als Eigenschaft gelesen ($link->confirmer).
        if (isset($index['properties'][$method]) && preg_match('/function ' . $method . '\(\)[^{]*\{[^}]*' . substr(self::RELATION, 1, -1) . '/s', $source) === 1) {
            return true;
        }

        return false;
    }

    /** Methode einer Framework- oder Paket-Elternklasse bzw. eines solchen Interfaces. */
    private function declaredByFramework(string $source, string $method): bool {
        if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1
            || preg_match('/^(?:final |abstract |readonly )*(?:class|trait|interface|enum)\s+(\w+)/m', $source, $class) !== 1) {
            return false;
        }
        $name = $namespace[1] . '\\' . $class[1];
        try {
            if (! class_exists($name) && ! interface_exists($name)) {
                return false;
            }
            $reflection = new ReflectionClass($name);
            $types = array_values($reflection->getInterfaces());
            for ($parent = $reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
                $types[] = $parent;
            }
            foreach ($types as $type) {
                if (str_starts_with($type->getName(), 'App\\')) {
                    continue;
                }
                if ($type->hasMethod($method)) {
                    return true;
                }
                // Haken, den das Paket über method_exists($this, 'name') aufruft (laravel/mcp: shouldRegister).
                $file = $type->getFileName();
                if (is_string($file) && str_contains((string) file_get_contents($file), "'" . $method . "'")) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /** @return list<string> öffentliche Methoden der Klasse auf oberster Ebene (ohne anonyme Klassen) */
    private function publicMethods(string $source): array {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $depth = 0;
        $classDepth = null;
        $modifiers = [];
        $methods = [];
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (! is_array($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                    if ($classDepth !== null && $depth === $classDepth) {
                        $classDepth = null;
                    }
                }
                if ($token === ';' || $token === '{' || $token === '}') {
                    $modifiers = [];
                }

                continue;
            }
            [$id] = $token;
            if (in_array($id, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                $depth++;
            } elseif (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $classDepth === null && ! $this->followsDoubleColon($tokens, $i)) {
                $classDepth = $depth;
            } elseif (in_array($id, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                $modifiers[] = $id;
            } elseif ($id === T_FUNCTION && $classDepth !== null && $depth === $classDepth + 1) {
                for ($j = $i + 1; $j < $count && (! is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING); $j++) {
                    if ($tokens[$j] === '(') {
                        break;
                    }
                }
                if (is_array($tokens[$j] ?? null) && ! in_array(T_PRIVATE, $modifiers, true) && ! in_array(T_PROTECTED, $modifiers, true)) {
                    $methods[] = $tokens[$j][1];
                }
                $modifiers = [];
            }
        }

        return $methods;
    }

    /** @param array<int, mixed> $tokens */
    private function followsDoubleColon(array $tokens, int $at): bool {
        for ($k = $at - 1; $k > 0; $k--) {
            if (is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                continue;
            }

            return is_array($tokens[$k]) && in_array($tokens[$k][0], [T_DOUBLE_COLON, T_NEW], true);
        }

        return false;
    }

    /** @return list<string> repo-relative Dateien im Geltungsbereich */
    private function scopeFiles(): array {
        $files = [];
        foreach ([...$this->phpFiles('app/Services'), ...$this->phpFiles('app/Plugins')] as $file) {
            $relative = $this->relativePath($file);
            if ($this->isDemoPath($relative)) {
                continue;
            }
            if (str_starts_with($relative, 'app/Services/') || str_starts_with($relative, 'app/Plugins/Support/') || preg_match('~^app/Plugins/[^/]+/Services/~', $relative) === 1) {
                $files[] = $relative;
            }
        }

        return $files;
    }

    /** @return array{calls: array<string, true>, strings: array<string, true>, properties: array<string, true>} */
    private function index(): array {
        if (self::$index !== null) {
            return self::$index;
        }
        $calls = $strings = $properties = [];
        $corpus = $this->codeCorpus(['app', 'routes', 'config', 'bootstrap', 'database/migrations', 'database/seeders', 'scripts']);
        $corpus += $this->codeCorpus(['resources/views'], '/\.blade\.php$/');
        foreach ($corpus as $source) {
            if (preg_match_all('/(?:->|::)\s*([A-Za-z_]\w*)\s*\(/', $source, $m) > 0) {
                $calls += array_fill_keys($m[1], true);
            }
            if (preg_match_all('/[\'"@]([A-Za-z_]\w*)[\'"]/', $source, $m) > 0) {
                $strings += array_fill_keys($m[1], true);
            }
            if (preg_match_all('/->\s*([A-Za-z_]\w*)\b(?!\s*\()/', $source, $m) > 0) {
                $properties += array_fill_keys($m[1], true);
            }
        }

        return self::$index = ['calls' => $calls, 'strings' => $strings, 'properties' => $properties];
    }
}
