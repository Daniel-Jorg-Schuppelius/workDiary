<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Api;

use App\Plugins\Jira\JiraConfig;
use App\Plugins\Jira\Models\JiraConnection;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Jira-Client zur Verbindung einer Organisation. Die
 * {@see PluginHttpFactory} wird erst zur Aufrufzeit gelöst — Tests binden
 * den Fake-Transport.
 */
class JiraClientFactory {
    public function isConfigured(): bool {
        return JiraConfig::isConfigured();
    }

    public function for(int $organizationId): JiraClient {
        $config = JiraConfig::resolve();
        if (! $this->isConfigured()) {
            throw new RuntimeException('Jira ist nicht konfiguriert (Client-ID/Secret der Installation fehlen).');
        }
        $connection = JiraConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
        if (! $connection instanceof JiraConnection) {
            throw new RuntimeException('Keine Jira-Verbindung für diese Organisation.');
        }

        return new JiraClient(app(PluginHttpFactory::class), $connection, $config['api_base'], $config['page_size'], $config['max_pages']);
    }
}
