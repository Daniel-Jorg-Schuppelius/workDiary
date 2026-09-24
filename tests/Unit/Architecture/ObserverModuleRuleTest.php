<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ObserverModuleRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Enums\Modules\ModuleKind;
use App\Modules\ModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-863: Ein Observer in `app/Observers` importiert nur Dienste seines
 * eigenen Moduls (Modul des beobachteten Modells) oder der Plattform;
 * modulübergreifende Reaktionen sind Domain-Events mit Listener im
 * reagierenden Modul.
 */
class ObserverModuleRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_observers_only_use_services_of_their_own_module_or_the_platform(): void {
        $registry = $this->app->make(ModuleRegistry::class);
        $violations = [];
        foreach ($this->phpFiles('app/Observers') as $file) {
            $source = (string) file_get_contents($file);
            $modelClass = $this->observedModel($source);
            if ($modelClass === null) {
                continue;
            }
            /** @var Model $model */
            $model = new $modelClass;
            $own = $registry->byTable($model->getTable());
            if ($own === null) {
                continue;
            }
            foreach ($this->serviceImports($source) as $class) {
                $folder = explode('\\', substr($class, strlen('App\\Services\\')))[0];
                $target = $registry->byFolder($folder);
                if ($target === null || $target->code() === $own->code() || $target->kind() === ModuleKind::Platform) {
                    continue;
                }
                $violations[] = sprintf('%s (%s) → %s (%s)', $this->relativePath($file), $own->code(), $class, $target->code());
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Observer mit fremdem Modulimport — als Domain-Event mit Listener im Zielmodul ausdrücken (MVP-863):\n" . implode("\n", $violations));
    }

    /** @return class-string<Model>|null Modell des ersten Methodenparameters */
    private function observedModel(string $source): ?string {
        if (preg_match('/public function \w+\((\w+) \$/', $source, $m) !== 1) {
            return null;
        }
        $short = $m[1];
        if (preg_match('/^use (App\\\\Models\\\\[\w\\\\]+\\\\' . preg_quote($short, '/') . ');/m', $source, $u) === 1) {
            return class_exists($u[1]) ? $u[1] : null;
        }
        if (preg_match_all('/^use (App\\\\Models\\\\[\w\\\\]+)\\\\\{([^}]+)\};/m', $source, $g, PREG_SET_ORDER) > 0) {
            foreach ($g as $group) {
                foreach (array_map('trim', explode(',', $group[2])) as $entry) {
                    if ($entry === $short) {
                        $fq = $group[1] . '\\' . $entry;

                        return class_exists($fq) ? $fq : null;
                    }
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function serviceImports(string $source): array {
        $out = [];
        if (preg_match_all('/^use (App\\\\Services\\\\[\w\\\\]+);/m', $source, $m) > 0) {
            $out = $m[1];
        }
        if (preg_match_all('/^use (App\\\\Services\\\\[\w\\\\]+)\\\\\{([^}]+)\};/m', $source, $g, PREG_SET_ORDER) > 0) {
            foreach ($g as $group) {
                foreach (array_map('trim', explode(',', $group[2])) as $entry) {
                    $out[] = $group[1] . '\\' . $entry;
                }
            }
        }
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $source);
        if (preg_match_all('/\\\\(App\\\\Services\\\\\w+\\\\[\w\\\\]*\w)(?=::|\s|\(|\)|,|;)/', $code, $inline) > 0) {
            $out = array_merge($out, $inline[1]);
        }

        return array_values(array_filter($out, static fn (string $fq): bool => ! str_contains($fq, '\\Contracts\\') && ! str_starts_with($fq, 'App\\Services\\Concerns\\')));
    }
}
