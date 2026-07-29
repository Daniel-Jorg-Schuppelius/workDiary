<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Api;

use App\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\Support\PluginHttpFactory;
use GuzzleHttp\Client as GuzzleClient;
use Orgamax\API\Client;

/**
 * Baut den SDK-Client (`orgamax-php-sdk`) je Verbindung: Bearer-Client für
 * alle Fachrouten, Basic-Auth-Client ausschließlich für POST /auth/token.
 * Endpunkte werden an der Aufrufstelle instanziiert
 * (z. B. `new InvoicesEndpoint($client)`).
 *
 * Die {@see PluginHttpFactory} wird bewusst ERST zur Aufrufzeit aus dem
 * Container gelöst: der Dispatcher wird beim Plugin-Boot registriert, Tests
 * binden den Fake-Transport (FakePluginHttp) aber erst danach.
 */
class OrgaMaxClientFactory {
    public function __construct(private readonly OrgaMaxTokenService $tokens) {}

    /** Authentifizierter Client (Token wird bei Bedarf erneuert). */
    public function for(OrgaMaxConnection $connection): Client {
        return $this->bearer($this->tokens->validTokenFor($connection));
    }

    /** Client mit einem bereits bekannten Token — ohne Erneuerungslauf. */
    public function bearer(string $token): Client {
        return $this->build(fn(?GuzzleClient $transport) => new Client($token, self::baseUrl(), null, false, $transport));
    }

    /** Basic-Auth: trägt nur die Auth-Route (Key/Secret gegen ownershipId). */
    public function credentials(string $apiKey, string $apiSecret): Client {
        return $this->build(fn(?GuzzleClient $transport) => Client::forCredentials($apiKey, $apiSecret, self::baseUrl(), null, false, $transport));
    }

    /**
     * Host-Basis der API. Ein historisch konfiguriertes `/openapi`-Suffix wird
     * entfernt — den Basispfad setzt der SDK-Client selbst.
     */
    public static function baseUrl(): string {
        $url = rtrim((string) config('plugins.orgamax.base_url', Client::DEFAULT_BASE_URL), '/');

        return str_ends_with($url, Client::BASE_PATH)
            ? mb_substr($url, 0, -mb_strlen(Client::BASE_PATH))
            : $url;
    }

    /** @param callable(GuzzleClient|null): Client $make */
    private function build(callable $make): Client {
        return app(PluginHttpFactory::class)->sdkClient(OrgaMaxPlugin::ID, self::baseUrl(), $make);
    }
}
