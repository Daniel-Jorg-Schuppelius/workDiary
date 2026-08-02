<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginFqcnMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{PluginError, PluginState};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daten-Migration zu W0a: FQCN-Waisen (Boot-Fehler wurden bis zum Fix unter
 * dem Klassennamen aufgezeichnet) werden auf die Plugin-ID umgeschrieben bzw.
 * entfernt, wenn die Klasse nicht mehr auflösbar ist.
 */
class PluginFqcnMigrationTest extends TestCase {
    use RefreshDatabase;

    private function runMigration(): void {
        $migration = require base_path('database/migrations/2026_11_13_100000_fix_plugin_fqcn_ids.php');
        $migration->up();
    }

    public function test_rewrites_resolvable_fqcn_rows_to_plugin_id(): void {
        PluginError::create([
            'plugin_id' => \App\Plugins\Toggl\TogglPlugin::class,
            'phase' => 'boot',
            'exception_class' => 'X',
            'message' => 'boom',
            'occurred_at' => now(),
        ]);
        PluginState::create([
            'plugin_id' => \App\Plugins\Toggl\TogglPlugin::class,
            'failure_count' => 3,
        ]);

        $this->runMigration();

        $this->assertSame(1, PluginError::query()->where('plugin_id', 'toggl')->count());
        $this->assertSame(0, PluginError::query()->where('plugin_id', 'like', '%TogglPlugin%')->count());
        $this->assertSame(3, (int) PluginState::query()->where('plugin_id', 'toggl')->firstOrFail()->failure_count);
    }

    public function test_drops_fqcn_state_row_when_target_row_exists(): void {
        PluginState::create(['plugin_id' => 'toggl', 'failure_count' => 1]);
        PluginState::create([
            'plugin_id' => \App\Plugins\Toggl\TogglPlugin::class,
            'failure_count' => 9,
        ]);

        $this->runMigration();

        $rows = PluginState::query()->where('plugin_id', 'toggl')->get();
        $this->assertCount(1, $rows);
        // Bestehende Ziel-Zeile gewinnt; die FQCN-Waise trug nur veraltete Boot-Info.
        $this->assertSame(1, (int) $rows->first()->failure_count);
        $this->assertSame(0, PluginState::query()->where('plugin_id', 'like', '%TogglPlugin%')->count());
    }

    public function test_deletes_rows_of_unresolvable_classes(): void {
        PluginError::create([
            'plugin_id' => 'App\\Plugins\\Gone\\GonePlugin',
            'phase' => 'boot',
            'exception_class' => 'X',
            'message' => 'boom',
            'occurred_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame(0, PluginError::query()->where('plugin_id', 'like', '%GonePlugin%')->count());
    }
}
