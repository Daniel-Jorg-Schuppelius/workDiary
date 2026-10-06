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

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\PluginHealthService;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
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
     * Client aus der für eine Organisation aufgelösten Konfiguration — nimmt
     * deren Anfrageabstand, auch wo kein Organisationskontext gebunden ist
     * (Konsole, Queue).
     *
     * @param  array{api_key: ?string, base_url: string, request_interval?: float}  $config  {@see LexofficeConfig::resolve()}
     */
    public function fromConfig(array $config): PluginApiClient {
        return $this->make((string) $config['api_key'], (string) $config['base_url'], $config['request_interval'] ?? null);
    }

    public function make(string $apiKey, string $baseUrl, ?float $requestInterval = null): PluginApiClient {
        $client = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $baseUrl, $requestInterval ?? LexofficeConfig::requestInterval());
        $client->setAuthentication(new BearerAuthentication($apiKey));
        // Der Health-Check behält das knappe Budget, das der Client sich selbst gibt.
        if (! PluginHealthService::inHealthCheck()) {
            $client->setMaxRetries(self::MAX_RETRIES);
        }

        return $client;
    }

    /** Client des Lexoffice-SDK (Kontakte, Belege, Dateien) mit demselben Transport. */
    public function sdk(string $apiKey, string $baseUrl): Client {
        return app(PluginHttpFactory::class)->sdkClient(
            LexofficePlugin::ID,
            $baseUrl,
            static fn (?GuzzleClient $transport): Client => new Client($apiKey, $baseUrl, null, false, $transport),
        );
    }
}
