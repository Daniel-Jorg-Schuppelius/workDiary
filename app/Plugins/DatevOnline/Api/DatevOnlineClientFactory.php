<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Plugins\DatevOnline\{DatevOnlineConfig, DatevOnlinePlugin};
use App\Plugins\DatevOnline\Models\DatevOnlineConnection;
use App\Plugins\Support\{ConnectionTokenStore, PluginHttpFactory};
use Datev\API\Online\{Client, OnlineService};
use GuzzleHttp\Client as GuzzleClient;

/**
 * SDK-Clients je DATEV-Dienst über die Plugin-HTTP-Fabrik: Bearer aus der
 * Verbindung (Erneuerung über das Refresh-Token), `X-DATEV-Client-Id` aus der
 * App-Registrierung, Sandbox nach Einstellung.
 */
class DatevOnlineClientFactory {
    public function for(DatevOnlineConnection $connection, OnlineService $service): Client {
        $organizationId = (int) $connection->organization_id;
        $config = DatevOnlineConfig::resolve($organizationId);
        $authentication = new OAuth2BearerAuthentication(
            new ConnectionTokenStore($connection),
            app(DatevOnlineOAuth::class)->grantFor($organizationId),
        );

        /** @var Client $client */
        $client = app(PluginHttpFactory::class)->sdkClient(
            DatevOnlinePlugin::ID,
            $service->host(),
            static fn (?GuzzleClient $transport): Client => new Client($service, $authentication, $config['client_id'], $config['sandbox'], httpClient: $transport),
        );

        return $client;
    }
}
