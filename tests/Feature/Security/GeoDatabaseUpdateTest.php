<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeoDatabaseUpdateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Services\Security\GeoDatabaseUpdater;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-1021: monatliche Aktualisierung der lokalen IP-Geodatenbank. */
final class GeoDatabaseUpdateTest extends TestCase {
    /** Build 2026-09-01, jede IPv4-Adresse → Berlin. */
    private const FIXTURE = __DIR__ . '/../../Fixtures/geoip/test-city.mmdb';

    private string $directory;

    private string $target;

    protected function setUp(): void {
        parent::setUp();
        $this->directory = storage_path('framework/testing/geoip-' . bin2hex(random_bytes(4)));
        $this->target = $this->directory . '/city.mmdb';
        config()->set('geoip.database', $this->target);
        config()->set('geoip.update.enabled', true);
        config()->set('geoip.update.url', 'https://download.db-ip.com/free/dbip-city-lite-{month}.mmdb.gz');
        $this->travelTo('2026-10-03 04:00:00');
    }

    protected function tearDown(): void {
        if (Folder::exists($this->directory)) {
            Folder::delete($this->directory, true);
        }
        parent::tearDown();
    }

    public function test_falls_back_to_the_previous_month_and_installs_the_database(): void {
        $http = FakePluginHttp::fake([
            'download.db-ip.com/free/dbip-city-lite-2026-10.mmdb.gz' => FakePluginHttp::response('', 404),
            'download.db-ip.com/free/dbip-city-lite-2026-09.mmdb.gz' => FakePluginHttp::response((string) gzencode(File::read(self::FIXTURE))),
        ]);

        $this->assertSame(0, Artisan::call('security:geoip-update'));
        $this->assertStringContainsString('2026-09', Artisan::output());
        $this->assertSame('2026-09', GeoDatabaseUpdater::buildOf($this->target)?->format('Y-m'));
        $this->assertFalse(File::exists($this->target . '.download'));
        $http->assertSentCount(2);

        // Der Vormonat ist installiert — kein erneuter Abruf.
        $this->assertSame(0, Artisan::call('security:geoip-update'));
        $http->assertSentCount(3);
    }

    public function test_a_broken_delivery_never_replaces_the_installed_database(): void {
        Folder::create($this->directory, 0755, true);
        File::copy(self::FIXTURE, $this->target);
        $before = File::hash($this->target);

        foreach (['kein gzip' => 'kaputt', 'keine Datenbank' => (string) gzencode('keine mmdb')] as $body) {
            FakePluginHttp::fake(['download.db-ip.com/*' => FakePluginHttp::response($body)]);

            $this->assertSame(1, Artisan::call('security:geoip-update', ['--force' => true]));
            $this->assertSame($before, File::hash($this->target));
            $this->assertFalse(File::exists($this->target . '.download'));
        }
    }

    public function test_the_job_stays_idle_until_it_is_switched_on(): void {
        config()->set('geoip.update.enabled', false);
        $http = FakePluginHttp::fake();

        $this->assertSame(0, Artisan::call('security:geoip-update'));
        $this->assertStringContainsString('GEOIP_AUTO_UPDATE', Artisan::output());
        $http->assertNothingSent();

        config()->set('geoip.update.enabled', true);
        config()->set('geoip.database', null);
        $this->assertSame(1, Artisan::call('security:geoip-update'));
        $http->assertNothingSent();
    }
}
