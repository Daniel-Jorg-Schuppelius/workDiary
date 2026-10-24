<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja\Http\Controllers;

use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};
use App\Plugins\Vikunja\Api\VikunjaClientFactory;
use App\Plugins\Vikunja\Models\{VikunjaConnection, VikunjaProjectLink};
use App\Plugins\Vikunja\Services\VikunjaImportService;
use App\Plugins\Vikunja\{VikunjaConfig, VikunjaPlugin};

/**
 * Vikunja-Verbindungsseite (MVP-1340): „Verbindung prüfen“ liest die Instanz
 * aus den Plugin-Einstellungen; Quellen werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<VikunjaProjectLink, VikunjaConnection>
 */
class VikunjaAdminController extends TokenTaskImportAdminController {
    protected function importService(): VikunjaImportService {
        return app(VikunjaImportService::class);
    }

    protected function linkModel(): string {
        return VikunjaProjectLink::class;
    }

    protected function connectionModel(): string {
        return VikunjaConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(VikunjaClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) VikunjaConfig::resolve($organizationId)['base_url'];
        $account = app(VikunjaClientFactory::class)->for($organizationId)->account();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl) . ($account['version'] !== null ? ' · ' . $account['version'] : ''),
            'source_url' => $baseUrl,
            'account_id' => $account['id'],
            'account_name' => $account['name'],
        ];
    }

    protected function providerName(): string {
        return 'Vikunja';
    }

    protected function sourceNoun(): string {
        return (string) __('vikunja::vikunja.source');
    }

    protected function pluginId(): string {
        return VikunjaPlugin::ID;
    }

    protected function isConfigured(): bool {
        return VikunjaConfig::isConfigured();
    }
}
