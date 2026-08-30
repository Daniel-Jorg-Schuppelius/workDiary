<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsrfGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{CalDavConnection, WebdavConnection, ZammadConnection};
use App\Plugins\CalDav\Services\HttpCalDavGateway;
use App\Plugins\Support\PluginApiClient;
use App\Plugins\Webdav\Services\HttpWebdavGateway;
use App\Plugins\Zammad\Services\ZammadClientGateway;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression zum Whitebox-Befund 2026-07 (MVP-099): org-konfigurierte
 * Basis-URLs der On-Premise-/Ticket-Anbindungen müssen öffentlich routbar sein.
 * Ein Loopback-/Metadata-/RFC1918-Ziel wird abgelehnt (SSRF-Schutz), damit ein
 * Org-Admin ausgehende Requests nicht auf interne Dienste richten kann.
 */
final class SsrfGuardTest extends TestCase {
    public function test_webdav_gateway_rejects_loopback_base_url(): void {
        $connection = new WebdavConnection();
        $connection->base_url = 'http://127.0.0.1/remote.php/dav';

        $this->expectException(RuntimeException::class);
        new HttpWebdavGateway(new PluginApiClient('webdav', (string) $connection->base_url), $connection);
    }

    public function test_caldav_gateway_rejects_private_base_url(): void {
        $connection = new CalDavConnection();
        $connection->base_url = 'http://10.0.0.5/remote.php/dav';

        $this->expectException(RuntimeException::class);
        new HttpCalDavGateway(new PluginApiClient('caldav', (string) $connection->base_url), $connection);
    }

    public function test_zammad_gateway_rejects_metadata_base_url(): void {
        $connection = new ZammadConnection();
        $connection->base_url = 'http://169.254.169.254/';
        $connection->api_token = 'dummy';

        $this->expectException(RuntimeException::class);
        ZammadClientGateway::forConnection($connection);
    }

    // ── Zentrale Schranke der Plugin-Factory (S-10, 2026-08-30) ─────────
    //
    // Vorher hatte jedes Gateway seinen eigenen Guard — acht Plugins hatten
    // gar keinen. Jetzt kommt keiner mehr an der Factory vorbei.

    /** @return array<string, array{0: string}> */
    public static function internalTargetProvider(): array {
        return [
            'Loopback' => ['http://127.0.0.1:8080/api'],
            'privates Netz' => ['http://10.0.0.5/api'],
            'Metadatendienst' => ['http://169.254.169.254/latest/meta-data/'],
            'Hex-Schreibweise' => ['http://0xa9fea9fe/latest/meta-data/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('internalTargetProvider')]
    public function test_plugin_factory_weist_interne_ziele_ab(string $baseUrl): void {
        $this->expectException(RuntimeException::class);

        app(\App\Plugins\Support\PluginHttpFactory::class)->client('kimai', $baseUrl);
    }

    public function test_plugin_factory_laesst_oeffentliche_ziele_durch(): void {
        // Gegenprobe — sonst wäre die Schranke nur ein Ausschalter.
        $client = app(\App\Plugins\Support\PluginHttpFactory::class)->client('kimai', 'https://kimai.example.com');

        $this->assertInstanceOf(PluginApiClient::class, $client);
    }

    public function test_selbst_gehostetes_ziel_braucht_das_opt_in(): void {
        // Wer eine eigene Instanz betreibt, sagt es ausdrücklich.
        $client = app(\App\Plugins\Support\PluginHttpFactory::class)
            ->client('kimai', 'http://10.0.0.5/api', allowPrivateNetwork: true);

        $this->assertInstanceOf(PluginApiClient::class, $client);
    }

    public function test_kern_dienste_laufen_durch_dieselbe_schranke(): void {
        $this->expectException(RuntimeException::class);

        app(\App\Plugins\Support\PluginHttpFactory::class)->coreClient('csaf', 'http://127.0.0.1/provider-metadata.json');
    }

}
