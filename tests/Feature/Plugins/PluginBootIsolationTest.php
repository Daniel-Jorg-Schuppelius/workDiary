<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginBootIsolationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\PluginError;
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginManager};
use App\Providers\PluginServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boot-Isolation des Plugin-Systems (Review 2026-08, W0a/W0b):
 *  - Fehler beim Instanziieren/Registrieren werden unter der Plugin-ID
 *    (const ID) aufgezeichnet, nicht unter dem FQCN — sonst greift der
 *    Auto-Disable nie und die Admin-UI kann den Fehler weder zuordnen
 *    noch zurücksetzen.
 *  - Eine doppelte Plugin-ID reißt nicht die ganze App, sondern überspringt
 *    das zweite Plugin und zeichnet den Konflikt auf.
 */
class PluginBootIsolationTest extends TestCase {
    use RefreshDatabase;

    private function rebuildManager(): PluginManager {
        // Provider erneut registrieren: die Singleton-Closure captured die
        // Klassenliste zum register()-Zeitpunkt — erst dadurch greift die
        // Test-Config `plugins.classes`.
        (new PluginServiceProvider($this->app))->register();
        $this->app->forgetInstance(PluginManager::class);

        return $this->app->make(PluginManager::class);
    }

    public function test_duplicate_plugin_id_is_skipped_and_recorded_under_plugin_id(): void {
        config()->set('plugins.classes', [BootDupPluginA::class, BootDupPluginB::class]);

        $manager = $this->rebuildManager();

        $this->assertInstanceOf(BootDupPluginA::class, $manager->find('wd-dup-test'));

        $error = PluginError::query()->where('plugin_id', 'wd-dup-test')->firstOrFail();
        $this->assertSame(PluginError::PHASE_BOOT, $error->phase);
        $this->assertStringContainsString('already registered', $error->message);
    }

    public function test_constructor_failure_is_recorded_under_plugin_id_not_fqcn(): void {
        config()->set('plugins.classes', [BootBoomPlugin::class]);

        $manager = $this->rebuildManager();

        $this->assertNull($manager->find('wd-boom-test'));

        $this->assertSame(1, PluginError::query()->where('plugin_id', 'wd-boom-test')->count());
        $this->assertSame(0, PluginError::query()->where('plugin_id', 'like', '%BootBoomPlugin%')->count());
    }
}

final class BootDupPluginA implements Plugin {
    use PluginDefaults;

    public const ID = 'wd-dup-test';

    public function id(): string {
        return self::ID;
    }
    public function name(): string {
        return 'Dup A';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return false;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
}

final class BootDupPluginB implements Plugin {
    use PluginDefaults;

    public const ID = 'wd-dup-test';

    public function id(): string {
        return self::ID;
    }
    public function name(): string {
        return 'Dup B';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return false;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
}

final class BootBoomPlugin implements Plugin {
    use PluginDefaults;

    public const ID = 'wd-boom-test';

    public function __construct() {
        throw new \RuntimeException('constructor boom');
    }

    public function id(): string {
        return self::ID;
    }
    public function name(): string {
        return 'Boom';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return false;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
}
