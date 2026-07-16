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
use App\Plugins\Nextcloud\NextcloudConfig;
use GuzzleHttp\Client;
use SensitiveParameter;

/**
 * Standard-Factory: baut je Anbindung einen {@see NextcloudWebdavClient} mit
 * frischem Guzzle-Client. Timeout, Chunk-Größe und die On-Premise-Freigabe
 * interner Ziele kommen aus der Plugin-Konfiguration. Im Test durch eine
 * Fake-Factory mit MockHandler ersetzt.
 */
class GuzzleNextcloudTransportFactory implements NextcloudTransportFactory {
    public function forCredentials(string $serverUrl, string $username, #[SensitiveParameter] string $appPassword): NextcloudWebdavClient {
        $config = NextcloudConfig::resolve();

        return new NextcloudWebdavClient(
            new Client(['timeout' => (int) $config['timeout']]),
            $serverUrl,
            $username,
            $appPassword,
            (bool) $config['allow_private_targets'],
            (int) $config['chunk_size'],
        );
    }
}
