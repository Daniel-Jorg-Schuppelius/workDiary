<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuzzleWebdavGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Services;

use App\Models\WebdavConnection;
use App\Plugins\PluginHealthService;
use App\Plugins\Support\Mirror\RemoteFileGateway;
use App\Plugins\Support\PluginHttpFactory;
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use App\Plugins\Webdav\WebdavPlugin;

/**
 * Standard-Factory (Feature 058): baut je Anbindung ein {@see HttpWebdavGateway}
 * mit einem {@see \App\Plugins\Support\PluginApiClient} aus der
 * {@see PluginHttpFactory} (C4-Rest 2026-08) — Tests ersetzen die Factory
 * bzw. den Guzzle-Transport (FakePluginHttp/MockHandler).
 */
class GuzzleWebdavGatewayFactory implements WebdavGatewayFactory {
    public function for(WebdavConnection $connection): RemoteFileGateway {
        $client = app(PluginHttpFactory::class)->client(WebdavPlugin::ID, (string) $connection->base_url);
        // DAV statt JSON-API; Timeout wie zuvor 30 s (Health-Check behält
        // sein reduziertes Budget aus dem PluginApiClient).
        $client->setDefaultHeaders([]);
        if (! PluginHealthService::inHealthCheck()) {
            $client->setTimeout(30.0);
        }

        return new HttpWebdavGateway($client, $connection);
    }
}
