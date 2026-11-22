<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeferredObserverRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Beobachter melden sich über `DeferredObservers::observe()` an (Phase 188, MVP-1801):
 * `Model::observe()` im Provider fährt das Modell beim Start jeder Anfrage hoch — vor dem
 * Umbau 57 Modelle und 9 ms je Anfrage.
 */
final class DeferredObserverRuleTest extends TestCase {
    use ScansSourceTree;

    /** Der Baustein selbst ruft `observe()`, sobald das Modell hochgefahren ist. */
    private const array ALLOWED = ['app/Support/Database/DeferredObservers.php'];

    public function test_observers_are_registered_through_the_deferred_registry(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }
            foreach ($this->directObserveLines((string) file_get_contents($file)) as $line) {
                $violations[] = $relative . ':' . $line;
            }
        }

        $this->assertSame([], $violations, "Model::observe() fährt das Modell bei jeder Anfrage hoch — \\App\\Support\\Database\\DeferredObservers::observe(Modell::class, Beobachter::class) nehmen:\n" . implode("\n", $violations));
    }

    public function test_the_rule_tells_direct_from_deferred_registration(): void {
        $this->assertSame([2], $this->directObserveLines("<?php\nTask::observe(TaskObserver::class);"));
        $this->assertSame([1], $this->directObserveLines('<?php $model::observe($observer);'));
        $this->assertSame([], $this->directObserveLines('<?php \App\Support\Database\DeferredObservers::observe(Task::class, TaskObserver::class);'));
        $this->assertSame([], $this->directObserveLines("<?php\n// Task::observe(TaskObserver::class);\n\$this->observe(\$x);"));
    }

    /** @return list<int> */
    private function directObserveLines(string $source): array {
        $source = $this->stripComments($source);
        $lines = [];
        if (preg_match_all('/(?<![\w\\\\])((?:\\\\?[A-Z][\w\\\\]*)|\$\w+)::observe\(/', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[1] as [$class, $offset]) {
                if (! str_ends_with($class, 'DeferredObservers')) {
                    $lines[] = $this->lineOf($source, $offset);
                }
            }
        }

        return $lines;
    }
}
