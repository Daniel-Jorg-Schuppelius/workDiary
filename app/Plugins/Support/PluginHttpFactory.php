<?php
/*
 * Created on   : Thu Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHttpFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

/**
 * Baut die {@see PluginApiClient}-Instanzen der Plugins. Als Container-
 * Singleton ist die Factory der Austauschpunkt für Tests: dort ersetzt
 * {@see \Tests\Support\FakePluginHttp} den Guzzle-Transport durch einen
 * Mock-Handler (Guzzle-`MockHandler`-Muster statt `Http::fake()`).
 */
class PluginHttpFactory {
    public function client(string $pluginId, string $baseUrl): PluginApiClient {
        return new PluginApiClient($pluginId, $baseUrl);
    }
}
