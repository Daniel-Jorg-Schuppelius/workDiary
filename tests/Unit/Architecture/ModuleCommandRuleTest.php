<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleCommandRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Console\Concerns\IteratesModuleOrganizations;
use App\Enums\Modules\ModuleKind;
use App\Modules\Attributes\RunsWhenModuleInactive;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use Tests\TestCase;

/**
 * Gate MVP-1524: Jeder eingeplante Befehl ohne Plugin-Bindung gehört einem
 * Modul (Domänenordner unter `App\Console\Commands` oder `#[BelongsToModule]`),
 * und jeder Befehl eines lizenzierten Fachmoduls läuft über
 * {@see IteratesModuleOrganizations} oder trägt {@see RunsWhenModuleInactive}
 * mit Grund. Plugin-Befehle prüft ihr Plugin-Schalter (`PluginCommandSwitchRuleTest`).
 */
class ModuleCommandRuleTest extends TestCase {
    public function test_every_scheduled_command_of_a_feature_module_is_gated(): void {
        $modules = $this->app->make(ModuleRegistry::class);
        $violations = [];
        foreach ($this->scheduledCommands() as $name => $class) {
            if (! str_starts_with($class, 'App\\')) {
                continue; // Framework-Befehle (model:prune) — Aufbewahrung über Prunable
            }
            $manifest = $modules->forClass($class);
            if ($manifest === null) {
                $violations[] = "{$name} ({$class}): kein Modul — in den Domänenordner seines Moduls legen oder #[BelongsToModule] tragen";

                continue;
            }
            $feature = $manifest->kind() === ModuleKind::Feature && $manifest->licenseCode() !== null;
            $gated = in_array(IteratesModuleOrganizations::class, class_uses_recursive($class), true);
            $exempt = $this->exemptionReason($class);
            if ($exempt === '') {
                $violations[] = "{$name} ({$class}): #[RunsWhenModuleInactive] ohne Grund";
            } elseif ($feature && ! $gated && $exempt === null) {
                $violations[] = "{$name} ({$class}, {$manifest->code()}): Fachmodul-Befehl ohne IteratesModuleOrganizations — oder #[RunsWhenModuleInactive] mit Grund (Aufbewahrung, Löschung, Sicherheit)";
            } elseif (! $feature && ($gated || $exempt !== null)) {
                $violations[] = "{$name} ({$class}, {$manifest->code()}): Modul-Gate in einem Plattform- oder Kernmodul prüft nichts";
            }
        }

        $this->assertSame([], $violations, "Geplante Befehle ohne Modul-Gate (MVP-1524):\n" . implode("\n", $violations));
    }

    public function test_the_rule_sees_the_known_feature_commands(): void {
        $commands = $this->scheduledCommands();

        $this->assertArrayHasKey('accounting:run-recurring', $commands);
        $this->assertContains(IteratesModuleOrganizations::class, class_uses_recursive($commands['accounting:run-recurring']));
        $this->assertNotNull($this->exemptionReason($commands['location:purge-points']));
        $this->assertGreaterThanOrEqual(80, count($commands));
    }

    /** @param class-string $class */
    private function exemptionReason(string $class): ?string {
        $attribute = (new ReflectionClass($class))->getAttributes(RunsWhenModuleInactive::class)[0] ?? null;

        return $attribute === null ? null : trim($attribute->newInstance()->reason);
    }

    /** @return array<string, class-string> Befehlsname → Klasse aller eingeplanten Befehle ohne Plugin-Bindung */
    private function scheduledCommands(): array {
        $artisan = Artisan::all();
        $out = [];
        foreach ((array) config('scheduler.jobs', []) as $key => $job) {
            if (! is_array($job) || ($job['plugin'] ?? null) !== null || ! is_string($job['command'] ?? null)) {
                continue;
            }
            $name = (string) strtok($job['command'], ' ');
            $this->assertArrayHasKey($name, $artisan, "Scheduler-Job {$key}: Befehl {$name} ist nicht registriert.");
            $out[$name] = $artisan[$name]::class;
        }

        return $out;
    }
}
