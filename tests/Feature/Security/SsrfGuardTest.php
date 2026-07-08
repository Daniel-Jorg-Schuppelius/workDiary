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
use App\Plugins\Webdav\Services\HttpWebdavGateway;
use App\Plugins\Zammad\Services\ZammadClientGateway;
use GuzzleHttp\Client as GuzzleClient;
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
        new HttpWebdavGateway(new GuzzleClient(), $connection);
    }

    public function test_caldav_gateway_rejects_private_base_url(): void {
        $connection = new CalDavConnection();
        $connection->base_url = 'http://10.0.0.5/remote.php/dav';

        $this->expectException(RuntimeException::class);
        new HttpCalDavGateway(new GuzzleClient(), $connection);
    }

    public function test_zammad_gateway_rejects_metadata_base_url(): void {
        $connection = new ZammadConnection();
        $connection->base_url = 'http://169.254.169.254/';
        $connection->api_token = 'dummy';

        $this->expectException(RuntimeException::class);
        ZammadClientGateway::forConnection($connection);
    }
}
