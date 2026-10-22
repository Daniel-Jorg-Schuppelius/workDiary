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
     * Client des Lexoffice-SDK: Transport und SSRF-Schranke der
     * {@see PluginHttpFactory}, Anfrageabstand der Organisation, Wiederholungsbudget.
     */
    public function sdk(string $apiKey, string $baseUrl, ?float $requestInterval = null): Client {
        $client = app(PluginHttpFactory::class)->sdkClient(
            LexofficePlugin::ID,
            $baseUrl,
            static fn (?GuzzleClient $transport): Client => new Client($apiKey, $baseUrl, null, false, $transport),
            // Ohne Angabe nähme sdkClient() den Abstand der gebundenen Organisation — Konsole und Queue binden keine.
            $requestInterval ?? LexofficeConfig::requestInterval(),
        );
        if (! PluginHealthService::inHealthCheck()) {
            $client->setMaxRetries(self::MAX_RETRIES);
        }

        return $client;
    }
}
