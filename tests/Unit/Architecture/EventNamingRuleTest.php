<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventNamingRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Enums\Modules\ModuleKind;
use App\Listeners\ModuleListener;
use App\Modules\ModuleRegistry;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-863: Domain-Events liegen unter `App\Events\<Domäne>`, Listener
 * unter `App\Listeners\<Domäne>`; Listener eines Fachmoduls erben von
 * {@see ModuleListener} (reagieren nur, wenn das Modul aktiv ist). Manifeste
 * registrieren nur Listener ihres eigenen Ordners.
 */
class EventNamingRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var list<string> Bestand vor MVP-863 (Plugin-Lebenszyklus, Auth-Subscriber, Mail-Zustellung) */
    private const ROOT_ALLOWLIST = [
        'app/Events/PluginAutoDisabled.php',
        'app/Events/PluginHealthChanged.php',
        'app/Events/PluginRecovered.php',
        'app/Listeners/AuthEventSubscriber.php',
        'app/Listeners/PluginEventSubscriber.php',
        'app/Listeners/RecordInvoiceMailDelivery.php',
        'app/Listeners/ModuleListener.php',
    ];

    /** @var array<string, string> Fachmodul-Listener ohne Modulgate, mit Grund */
    private const UNGATED = [
        'App\Listeners\Privacy\SeedDataProtectionRole' => 'Rollen entstehen unabhängig von der Modulaktivierung, sonst müsste eine spätere Freischaltung Rollen nachziehen.',
        'App\Listeners\Whistleblowing\SeedWhistleblowingRole' => 'wie SeedDataProtectionRole',
    ];

    public function test_events_and_listeners_live_in_domain_folders(): void {
        $violations = [];
        foreach (['app/Events', 'app/Listeners'] as $layer) {
            foreach ($this->phpFiles($layer) as $file) {
                $relative = $this->relativePath($file);
                if (in_array($relative, self::ROOT_ALLOWLIST, true)) {
                    continue;
                }
                $parts = explode('/', substr($relative, strlen($layer) + 1));
                if (count($parts) !== 2) {
                    $violations[] = $relative . ' — erwartet ' . $layer . '/<Domäne>/<Klasse>.php';
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Events/Listener außerhalb der Domänenordner:\n" . implode("\n", $violations));
    }

    public function test_feature_module_listeners_extend_module_listener(): void {
        $registry = $this->app->make(ModuleRegistry::class);
        $violations = [];
        foreach ($this->phpFiles('app/Listeners') as $file) {
            $relative = $this->relativePath($file);
            if (in_array($relative, self::ROOT_ALLOWLIST, true)) {
                continue;
            }
            $class = 'App\\' . str_replace('/', '\\', substr($relative, 4, -4));
            $folder = explode('\\', $class)[2];
            $manifest = $registry->byFolder($folder);
            if ($manifest === null || $manifest->kind() !== ModuleKind::Feature || isset(self::UNGATED[$class])) {
                continue;
            }
            if (! is_subclass_of($class, ModuleListener::class)) {
                $violations[] = $class . ' (' . $manifest->code() . ')';
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Listener eines Fachmoduls ohne ModuleListener-Basis (Modulgate):\n" . implode("\n", $violations));
    }

    public function test_manifests_register_only_their_own_listeners(): void {
        $registry = $this->app->make(ModuleRegistry::class);
        $violations = [];
        foreach ($registry->all() as $code => $manifest) {
            foreach ($manifest->listeners() as $event => $listeners) {
                if (! str_starts_with($event, 'App\\Events\\')) {
                    $violations[] = "{$code}: Event {$event} liegt nicht unter App\\Events.";
                }
                foreach ($listeners as $listener) {
                    $folder = explode('\\', $listener)[2] ?? '';
                    $owner = $registry->byFolder($folder);
                    if ($owner === null || $owner->code() !== $code) {
                        $violations[] = "{$code}: Listener {$listener} gehört nicht zu diesem Modul.";
                    }
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
