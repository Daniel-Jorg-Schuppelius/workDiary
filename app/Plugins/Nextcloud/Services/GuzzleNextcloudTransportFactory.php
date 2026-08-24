<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuzzleNextcloudTransportFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Services;

use App\Plugins\Nextcloud\Api\NextcloudWebdavClient;
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Nextcloud\{NextcloudConfig, NextcloudPlugin};
use App\Plugins\PluginHealthService;
use App\Plugins\Support\PluginHttpFactory;
use SensitiveParameter;

/**
 * Standard-Factory: baut je Anbindung einen {@see NextcloudWebdavClient} mit
 * einem {@see \App\Plugins\Support\PluginApiClient} aus der
 * {@see PluginHttpFactory} (C4-Rest 2026-08). Timeout, Chunk-Größe und die
 * On-Premise-Freigabe interner Ziele kommen aus der Plugin-Konfiguration.
 * Im Test durch eine Fake-Factory mit MockHandler-Transport ersetzt.
 */
class GuzzleNextcloudTransportFactory implements NextcloudTransportFactory {
    public function forCredentials(string $serverUrl, string $username, #[SensitiveParameter] string $appPassword): NextcloudWebdavClient {
        $config = NextcloudConfig::resolve();

        $client = app(PluginHttpFactory::class)->client(NextcloudPlugin::ID, rtrim(trim($serverUrl), '/'));
        // DAV statt JSON-API; Timeout wie zuvor aus der Plugin-Konfiguration
        // (Health-Check behält sein reduziertes Budget aus dem PluginApiClient).
        $client->setDefaultHeaders([]);
        if (! PluginHealthService::inHealthCheck()) {
            $client->setTimeout((float) $config['timeout']);
        }

        return new NextcloudWebdavClient(
            $client,
            $serverUrl,
            $username,
            $appPassword,
            (bool) $config['allow_private_targets'],
            (int) $config['chunk_size'],
        );
    }
}
