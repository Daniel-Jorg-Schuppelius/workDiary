<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatabaseConfiguratorRuntimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Install;

use App\Services\Install\{DatabaseConfigurator, EnvWriter};
use Illuminate\Cache\DatabaseStore;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\{Cache, Config};
use PDO;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ohne RefreshDatabase: der Test schaltet die Default-Verbindung um und darf
 * keine Test-Transaktion der eigentlichen Test-DB mitreißen. Beide Seiten sind
 * Wegwerf-SQLite-Dateien; die App wird pro Test neu gebootet.
 */
class DatabaseConfiguratorRuntimeTest extends TestCase {
    private string $dir;

    protected function setUp(): void {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/wd-dbswitch-' . uniqid();
        @mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_configure_database_rebinds_database_cache_and_permission_registrar(): void {
        // Vorher: Default-DB ohne cache-Tabelle (wie die leere database.sqlite
        // einer frischen Installation), Cache-Store und Registrar daran gebunden.
        $old = $this->dir . '/old.sqlite';
        touch($old);
        Config::set('database.connections.install_old', ['driver' => 'sqlite', 'database' => $old, 'prefix' => '']);
        Config::set('database.default', 'install_old');
        Config::set('cache.default', 'database');
        Config::set('cache.stores.database.connection', null);
        Config::set('permission.cache.store', 'default');
        Cache::purge('database');
        app()->forgetInstance(PermissionRegistrar::class);
        $registrar = app(PermissionRegistrar::class);
        $this->assertSame($old, $this->databaseStoreConnection()->getDatabaseName());

        // Ziel-DB mit cache-Tabelle (dort liegen die Migrationen).
        $new = $this->dir . '/new.sqlite';
        (new PDO('sqlite:' . $new))->exec('CREATE TABLE cache (key varchar(255) PRIMARY KEY, value text NOT NULL, expiration integer NOT NULL)');

        (new DatabaseConfigurator(new EnvWriter($this->dir . '/.env')))
            ->configureDatabase(['driver' => 'sqlite', 'database' => $new]);

        $this->assertSame($new, $this->databaseStoreConnection()->getDatabaseName());
        // Der bereits aufgelöste Registrar-Singleton darf nicht mehr in die Alt-DB löschen.
        $registrar->forgetCachedPermissions();
        Cache::store()->put('probe', 'ok', 60);
        $this->assertSame('ok', Cache::store()->get('probe'));
    }

    private function databaseStoreConnection(): ConnectionInterface {
        $store = Cache::store('database')->getStore();
        $this->assertInstanceOf(DatabaseStore::class, $store);

        return $store->getConnection();
    }
}
