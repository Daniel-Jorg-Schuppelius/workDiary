<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea\Http\Controllers;

use App\Plugins\Gitea\Api\GiteaClientFactory;
use App\Plugins\Gitea\{GiteaConfig, GiteaPlugin};
use App\Plugins\Gitea\Models\{GiteaConnection, GiteaProjectLink};
use App\Plugins\Gitea\Services\GiteaImportService;
use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};

/**
 * Gitea-/Forgejo-Verbindungsseite (MVP-1318/1320): „Verbindung prüfen“ liest
 * Konto und Version der Instanz aus den Plugin-Einstellungen; Repositories
 * werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<GiteaProjectLink, GiteaConnection>
 */
class GiteaAdminController extends TokenTaskImportAdminController {
    protected function importService(): GiteaImportService {
        return app(GiteaImportService::class);
    }

    protected function linkModel(): string {
        return GiteaProjectLink::class;
    }

    protected function connectionModel(): string {
        return GiteaConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(GiteaClientFactory::class)->for($organizationId)->repositories();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) GiteaConfig::resolve($organizationId)['base_url'];
        $client = app(GiteaClientFactory::class)->for($organizationId);
        $user = $client->currentUser();
        $version = $client->version();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl) . ($version !== '' ? ' · ' . $version : ''),
            'source_url' => $baseUrl,
            'account_id' => $user['id'],
            'account_name' => $user['login'],
        ];
    }

    protected function providerName(): string {
        return 'Gitea/Forgejo';
    }

    protected function sourceNoun(): string {
        return (string) __('gitea::gitea.source');
    }

    protected function pluginId(): string {
        return GiteaPlugin::ID;
    }

    protected function isConfigured(): bool {
        return GiteaConfig::isConfigured();
    }
}
