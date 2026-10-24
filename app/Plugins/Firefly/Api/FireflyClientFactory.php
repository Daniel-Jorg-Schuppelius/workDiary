<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Api;

use App\Plugins\Firefly\FireflyConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/** Baut den Firefly-III-Client aus den Plugin-Einstellungen einer Organisation. */
class FireflyClientFactory {
    public function isConfigured(int $organizationId): bool {
        return FireflyConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): FireflyClient {
        $config = FireflyConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['access_token'] === null) {
            throw new RuntimeException('Firefly III ist für diese Organisation nicht eingerichtet (Adresse oder Token fehlen).');
        }

        return new FireflyClient(app(PluginHttpFactory::class), $config['base_url'], $config['access_token'], $config['page_size'], $config['max_pages']);
    }
}
