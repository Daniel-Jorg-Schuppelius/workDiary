<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuzzleCalDavGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Models\CalDavConnection;
use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\CalDav\Contracts\{CalDavGateway, CalDavGatewayFactory};
use App\Plugins\PluginHealthService;
use App\Plugins\Support\PluginHttpFactory;

/**
 * Standard-Factory (Feature 058): baut je Anbindung ein {@see HttpCalDavGateway}
 * mit einem {@see \App\Plugins\Support\PluginApiClient} aus der
 * {@see PluginHttpFactory} (C4-Rest 2026-08) — Tests ersetzen die Factory
 * bzw. den Guzzle-Transport (FakePluginHttp/MockHandler).
 */
class GuzzleCalDavGatewayFactory implements CalDavGatewayFactory {
    public function for(CalDavConnection $connection): CalDavGateway {
        $client = app(PluginHttpFactory::class)->client(CalDavPlugin::ID, (string) $connection->base_url);
        // DAV statt JSON-API; Timeout wie zuvor 15 s (Health-Check behält
        // sein reduziertes Budget aus dem PluginApiClient).
        $client->setDefaultHeaders([]);
        if (! PluginHealthService::inHealthCheck()) {
            $client->setTimeout(15.0);
        }

        return new HttpCalDavGateway($client, $connection);
    }
}
