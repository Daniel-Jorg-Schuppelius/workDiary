<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceModeIsolationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\CacheBasedMaintenanceMode;
use Tests\TestCase;

/**
 * Ein Test, der den Wartungsmodus des Frameworks einschaltet, sperrt nur
 * seinen eigenen Prozess.
 *
 * Mit dem Dateitreiber lag `storage/framework/down` für alle parallelen
 * Worker sichtbar. Solange BackupHeartbeatEndpointTest den Modus hielt,
 * bekamen Anfragen anderer Worker 503; so war main am 2026-11-22 mit
 * B2bPunchoutTest rot. phpunit.xml.dist hält den Modus deshalb im
 * Array-Store des Prozesses.
 */
final class MaintenanceModeIsolationTest extends TestCase {
    public function test_maintenance_mode_writes_nothing_other_workers_read(): void {
        $downFile = storage_path('framework/down');
        $existedBefore = is_file($downFile);
        $mode = $this->app->make(MaintenanceMode::class);

        $this->assertInstanceOf(CacheBasedMaintenanceMode::class, $mode);
        $this->assertSame('array', config('app.maintenance.store'));

        $mode->activate([]);

        try {
            $this->assertTrue($this->app->isDownForMaintenance());
            $this->assertSame($existedBefore, is_file($downFile), 'Der Wartungsmodus eines Tests darf keine Datei schreiben, die parallele Worker lesen.');
        } finally {
            $mode->deactivate();
        }

        $this->assertFalse($this->app->isDownForMaintenance());
    }
}
