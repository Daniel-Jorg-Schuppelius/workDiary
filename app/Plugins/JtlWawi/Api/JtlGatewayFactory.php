<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Api;

use App\Models\JtlConnection;
use App\Plugins\Support\PluginHttpFactory;

/**
 * Baut je Verbindung ein {@see JtlGateway} (Feature 078). Der HTTP-Transport
 * kommt aus der {@see PluginHttpFactory} — Tests ersetzen sie über
 * `Tests\Support\FakePluginHttp::fake()` durch einen Guzzle-MockHandler.
 */
class JtlGatewayFactory {
    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly JtlCloudTokenService $tokens,
    ) {}

    public function for(JtlConnection $connection): JtlGateway {
        return new JtlGateway($connection, $this->http, $this->tokens);
    }
}
