<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Api;

use App\Plugins\Asana\AsanaConfig;
use App\Plugins\Asana\Models\AsanaConnection;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/** Baut den Asana-Client zur Verbindung einer Organisation; die HTTP-Fabrik wird erst zur Aufrufzeit gelöst. */
class AsanaClientFactory {
    public function isConfigured(): bool {
        return AsanaConfig::isConfigured();
    }

    public function for(int $organizationId): AsanaClient {
        $config = AsanaConfig::resolve();
        if (! $this->isConfigured()) {
            throw new RuntimeException('Asana ist nicht konfiguriert (Client-ID/Secret der Installation fehlen).');
        }
        $connection = AsanaConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
        if (! $connection instanceof AsanaConnection) {
            throw new RuntimeException('Keine Asana-Verbindung für diese Organisation.');
        }

        return new AsanaClient(app(PluginHttpFactory::class), $connection, $config['api_base'], $config['page_size'], $config['max_pages']);
    }
}
