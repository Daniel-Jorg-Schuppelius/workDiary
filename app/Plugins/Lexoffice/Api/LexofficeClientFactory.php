<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Api;

use App\Plugins\Lexoffice\Exceptions\LexofficeNotConfiguredException;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\PluginHealthService;
use App\Plugins\Support\PluginHttpFactory;
use GuzzleHttp\Client as GuzzleClient;
use Lexoffice\API\Client;

/**
 * Die eine Stelle, die Lexoffice-Clients baut (Konsolidierungs-Audit 2026-10,
 * k2-02): Schlüssel, Anfrageabstand der Organisation und Wiederholungsbudget
 * gelten damit für jeden Aufruf gleich. Die {@see PluginHttpFactory} wird erst
 * zur Aufrufzeit gelöst — Tests binden den Fake-Transport nach dem Plugin-Boot.
 */
class LexofficeClientFactory {
    /** Versuche je Anfrage (Toolkit-Retry inkl. Retry-After); Lexoffice erlaubt nur 2 Anfragen je Sekunde. */
    public const MAX_RETRIES = 5;

    /**
     * SDK-Client mit Schlüssel, Basis-URL und Anfrageabstand der Organisation;
     * `null` heißt gebundene Organisation bzw. Einzelplatz-Konfiguration
     * ({@see LexofficeConfig::resolve()}).
     *
     * @throws LexofficeNotConfiguredException
     */
    public function for(?int $organizationId): Client {
        $config = LexofficeConfig::resolve($organizationId);
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new LexofficeNotConfiguredException();
        }

        $baseUrl = $config['base_url'];
        $client = app(PluginHttpFactory::class)->sdkClient(
            LexofficePlugin::ID,
            $baseUrl,
            static fn (?GuzzleClient $transport): Client => new Client($apiKey, $baseUrl, null, false, $transport),
            $config['request_interval'],
        );
        if (! PluginHealthService::inHealthCheck()) {
            $client->setMaxRetries(self::MAX_RETRIES);
        }

        return $client;
    }

    public function isConfigured(?int $organizationId): bool {
        return (string) (LexofficeConfig::resolve($organizationId)['api_key'] ?? '') !== '';
    }
}
