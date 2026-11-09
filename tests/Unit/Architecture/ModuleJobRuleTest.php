<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleJobRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Enums\Modules\ModuleKind;
use App\Jobs\Contracts\ModuleJob;
use App\Jobs\Middleware\EnsureModuleActive;
use Illuminate\Contracts\Queue\ShouldQueue;
use ReflectionClass;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1518: Jeder Job unter `app/Jobs` gehört einem Modul (Domänenordner
 * oder `#[BelongsToModule]`), und jeder Job eines lizenzierten Fachmoduls
 * trägt {@see ModuleJob}, damit {@see EnsureModuleActive} ihn bei
 * abgeschaltetem Modul ohne Wirkung beendet. Plugin-Jobs prüft ihr
 * Plugin-Schalter; sie sind nicht Teil der Regel.
 */
class ModuleJobRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_every_job_of_a_feature_module_is_gated(): void {
        $gate = $this->app->make(EnsureModuleActive::class);
        $violations = [];
        foreach ($this->jobClasses() as $class) {
            $manifest = $gate->manifestFor($class);
            $gated = is_subclass_of($class, ModuleJob::class);
            if ($manifest === null) {
                $violations[] = "{$class}: kein Modul — in den Domänenordner seines Moduls legen oder #[BelongsToModule] tragen";

                continue;
            }
            $feature = $manifest->kind() === ModuleKind::Feature && $manifest->licenseCode() !== null;
            if ($feature && ! $gated) {
                $violations[] = "{$class} ({$manifest->code()}): Fachmodul-Job ohne ModuleJob";
            } elseif (! $feature && $gated) {
                $violations[] = "{$class} ({$manifest->code()}): ModuleJob in einem Plattform- oder Kernmodul prüft nichts";
            }
        }

        $this->assertSame([], $violations, "Queue-Jobs ohne Modul-Gate (MVP-1518):\n" . implode("\n", $violations));
    }

    public function test_the_rule_sees_the_known_feature_jobs(): void {
        $gated = array_values(array_filter($this->jobClasses(), static fn (string $class): bool => is_subclass_of($class, ModuleJob::class)));

        $this->assertContains(\App\Jobs\ServiceTicketReplyMailJob::class, $gated);
        $this->assertContains(\App\Jobs\Inventory\InventoryOutboxDeliveryJob::class, $gated);
        $this->assertGreaterThanOrEqual(8, count($gated));
    }

    /** @return list<class-string> konkrete Queue-Jobs unter `app/Jobs` */
    private function jobClasses(): array {
        $classes = [];
        foreach ($this->phpFiles('app/Jobs') as $file) {
            $relative = $this->relativePath($file);
            if (preg_match('~^app/Jobs/(Attributes|Concerns|Contracts|Middleware)/~', $relative) === 1) {
                continue;
            }
            $class = 'App\\' . str_replace('/', '\\', substr($relative, strlen('app/'), -strlen('.php')));
            if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $classes[] = $class;
        }

        return $classes;
    }
}
