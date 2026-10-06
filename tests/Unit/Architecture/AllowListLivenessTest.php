<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AllowListLivenessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use ReflectionClass;
use Tests\TestCase;
use Throwable;

/**
 * Gate zum Konsolidierungs-Audit 2026-10 (k3-17): ein Ausnahmeeintrag, der
 * keinen Verstoß mehr deckt, ist eine vorweg erteilte Ausnahme — kommt der
 * Verstoß in der Datei zurück, meldet das Gate nichts. 88 solcher Einträge
 * standen in 18 Gates.
 *
 * Verfahren: je Gate und Liste eine Kopie mit geleerter Liste laufen lassen
 * und prüfen, dass jeder Eintrag in den dann gemeldeten Verstößen vorkommt.
 */
class AllowListLivenessTest extends TestCase {
    /** Namen, die eine Ausnahmeliste kennzeichnen. */
    private const LIST_NAME = '/^(?:[A-Z_]*ALLOW(?:ED|_?LIST)?|[A-Z_]*_ALLOW|WHITELIST|EXCEPTIONS|ORG_WIDE|NOT_A_[A-Z_]+|[A-Z_]*_EXEMPT)$/';

    /**
     * Listen, die den Geltungsbereich eines Gates festlegen, statt einzelne
     * Verstöße auszunehmen — "Gate::KONSTANTE" => Grund.
     *
     * @var array<string, string>
     */
    private const SCOPE = [
        'AlpineCspExpressionRuleTest::ALLOW_LIST' => 'Legacy- und Vendor-Views liegen außerhalb der Konvention.',
        'InlineEventHandlerRuleTest::ALLOW_LIST' => 'Legacy- und Vendor-Views liegen außerhalb der Konvention.',
        'RawStatusOutputRuleTest::ALLOW_LIST' => 'Legacy- und Vendor-Views liegen außerhalb der Konvention.',
        'RawDigestRuleTest::WHITELIST' => 'Der Legacy-Bereich spricht die Alt-Datenbank mit ihren Verfahren.',
        'CardComponentRuleTest::PATH_EXEMPT' => 'Pfadmuster für Komponenten, Druck, PDF und Mail — dort gelten die Kartenregeln nicht.',
        'TableConventionRuleTest::RAW_TABLE_PATH_EXEMPT' => 'Pfadmuster für Komponenten, Druck, PDF und Mail — dort gelten die Tabellenregeln nicht.',
        'ColumnNamingRuleTest::NOT_A_COLUMN' => 'Blueprint-Methoden, die keine Spalte anlegen — keine Ausnahmeliste.',
        'PluginHelpMentionRuleTest::NOT_A_PLUGIN' => 'Ordner unter app/Plugins, die kein Plugin sind — keine Ausnahmeliste.',
        'SqidViewRuleTest::NOT_A_DATABASE_ID' => 'Bezeichner, die keine Datenbank-ID sind — Mustergrenze, keine Ausnahmeliste.',
    ];

    /**
     * Einträge, die bewusst vorsorglich stehen — "Gate::KONSTANTE:Eintrag" => Grund.
     *
     * @var array<string, string>
     */
    private const KEPT = [];

    /** @var list<string> */
    private static array $messages = [];

    public function test_every_allow_list_entry_still_covers_a_violation(): void {
        $stale = [];
        $broken = [];
        $checked = 0;

        foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
            $gate = basename($file, '.php');
            if ($gate === 'AllowListLivenessTest') {
                continue;
            }
            $reflection = new ReflectionClass(__NAMESPACE__ . '\\' . $gate);
            foreach ($reflection->getReflectionConstants() as $constant) {
                $name = $constant->getName();
                $value = $constant->getValue();
                if (preg_match(self::LIST_NAME, $name) !== 1 || ! is_array($value) || $value === [] || isset(self::SCOPE["$gate::$name"])) {
                    continue;
                }
                $entries = array_is_list($value) ? array_map(strval(...), array_filter($value, is_scalar(...))) : array_map(strval(...), array_keys($value));

                $report = $this->violationsWithout($file, $gate, $name);
                if ($report === null) {
                    $broken[] = "$gate::$name";

                    continue;
                }
                $checked++;
                foreach ($entries as $entry) {
                    if (isset(self::KEPT["$gate::$name:$entry"])) {
                        continue;
                    }
                    if (! str_contains($report, $entry) && ! str_contains($report, basename(str_replace('\\', '/', $entry)))) {
                        $stale[] = "$gate::$name — $entry";
                    }
                }
            }
        }

        $this->assertGreaterThan(20, $checked, 'Die Suche findet die Ausnahmelisten nicht mehr.');
        $this->assertSame([], $broken, "Liste lässt sich nicht geleert prüfen (Gate umgebaut?) — hier nachziehen oder in SCOPE begründen:\n" . implode("\n", $broken));
        $this->assertSame([], $stale, "Ausnahmeeintrag ohne Verstoß — streichen, oder in KEPT bzw. SCOPE begründen:\n" . implode("\n", $stale));
    }

    /** Verstöße, die das Gate mit geleerter Liste meldet; null, wenn die Kopie nicht läuft. */
    private function violationsWithout(string $file, string $gate, string $constant): ?string {
        $source = (string) file_get_contents($file);
        $copy = $gate . '__ohne_' . $constant;

        $source = (string) preg_replace('/(const ' . $constant . ' = )\[.*?\n    \];/s', '$1[];', $source, 1, $replaced);
        if ($replaced !== 1) {
            $source = (string) preg_replace('/(const ' . $constant . ' = )\[[^\]]*\];/s', '$1[];', $source, 1, $replaced);
        }
        if ($replaced !== 1) {
            return null;
        }
        $source = (string) preg_replace('/\bclass ' . $gate . '\b/', 'class ' . $copy, $source, 1);
        $source = str_replace(['__DIR__', '__FILE__'], [var_export(__DIR__, true), var_export($file, true)], $source);
        // Zusicherungen sammeln statt abbrechen: eine Methode prüft oft mehrere Listen.
        $source = (string) preg_replace('/(?:\$this->|self::|static::)assert(?:Same|Equals)\(/', '\\' . self::class . '::probeSame(', $source);
        $source = (string) preg_replace('/(?:\$this->|self::|static::)assertEmpty\(/', '\\' . self::class . '::probeEmpty(', $source);
        $source = (string) preg_replace('/(?:\$this->|self::|static::)assertCount\(0,/', '\\' . self::class . '::probeEmpty(', $source);
        $source = (string) preg_replace('/(?:\$this->|self::|static::)assertTrue\(/', '\\' . self::class . '::probeTrue(', $source);
        $source = (string) preg_replace('/^<\?php/', '', $source, 1);

        self::$messages = [];
        try {
            if (! class_exists(__NAMESPACE__ . '\\' . $copy, false)) {
                eval($source);
            }
            $class = __NAMESPACE__ . '\\' . $copy;
            $instance = new $class('liveness');
            if (property_exists($instance, 'app')) {
                (fn () => $this->app = app())->call($instance);
            }
            foreach ((new ReflectionClass($class))->getMethods() as $method) {
                if (! str_starts_with($method->getName(), 'test')) {
                    continue;
                }
                try {
                    $method->invoke($instance);
                } catch (Throwable $e) {
                    self::$messages[] = $e->getMessage();
                }
            }
        } catch (Throwable) {
            return null;
        }

        return implode("\n", self::$messages);
    }

    public static function probeSame(mixed $expected, mixed $actual, string $message = ''): void {
        if ($expected !== $actual) {
            self::$messages[] = $message . "\n" . self::dump($actual);
        }
    }

    public static function probeEmpty(mixed $actual, string $message = ''): void {
        if (! empty($actual)) {
            self::$messages[] = $message . "\n" . self::dump($actual);
        }
    }

    public static function probeTrue(mixed $condition, string $message = ''): void {
        if ($condition !== true) {
            self::$messages[] = $message;
        }
    }

    private static function dump(mixed $value): string {
        return is_array($value)
            ? implode("\n", array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : (string) json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $value))
            : (is_scalar($value) ? (string) $value : (string) json_encode($value));
    }
}
