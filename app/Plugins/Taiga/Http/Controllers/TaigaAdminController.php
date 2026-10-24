<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Taiga\Http\Controllers;

use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};
use App\Plugins\Taiga\Api\TaigaClientFactory;
use App\Plugins\Taiga\Models\{TaigaConnection, TaigaProjectLink};
use App\Plugins\Taiga\Services\TaigaImportService;
use App\Plugins\Taiga\{TaigaConfig, TaigaPlugin};

/**
 * Taiga-Verbindungsseite (MVP-1343): „Verbindung prüfen“ liest die Instanz
 * aus den Plugin-Einstellungen; Quellen werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<TaigaProjectLink, TaigaConnection>
 */
class TaigaAdminController extends TokenTaskImportAdminController {
    protected function importService(): TaigaImportService {
        return app(TaigaImportService::class);
    }

    protected function linkModel(): string {
        return TaigaProjectLink::class;
    }

    protected function connectionModel(): string {
        return TaigaConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(TaigaClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) TaigaConfig::resolve($organizationId)['base_url'];
        $client = app(TaigaClientFactory::class)->for($organizationId);
        $account = $client->account();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl),
            // Linkbasis ist die Oberfläche — in der Cloud ein anderer Host als die API.
            'source_url' => $client->appUrl(),
            'account_id' => $account['id'],
            'account_name' => $account['name'],
        ];
    }

    protected function providerName(): string {
        return 'Taiga';
    }

    protected function sourceNoun(): string {
        return (string) __('taiga::taiga.source');
    }

    protected function pluginId(): string {
        return TaigaPlugin::ID;
    }

    protected function isConfigured(): bool {
        return TaigaConfig::isConfigured();
    }
}
