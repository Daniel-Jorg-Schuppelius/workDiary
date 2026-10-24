<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi\Http\Controllers;

use App\Plugins\Glpi\Api\GlpiClientFactory;
use App\Plugins\Glpi\{GlpiConfig, GlpiPlugin};
use App\Plugins\Glpi\Models\{GlpiConnection, GlpiProjectLink};
use App\Plugins\Glpi\Services\GlpiImportService;
use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};

/**
 * GLPI-Verbindungsseite (MVP-1337): „Verbindung prüfen“ liest die Instanz
 * aus den Plugin-Einstellungen; Quellen werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<GlpiProjectLink, GlpiConnection>
 */
class GlpiAdminController extends TokenTaskImportAdminController {
    protected function importService(): GlpiImportService {
        return app(GlpiImportService::class);
    }

    protected function linkModel(): string {
        return GlpiProjectLink::class;
    }

    protected function connectionModel(): string {
        return GlpiConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(GlpiClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) GlpiConfig::resolve($organizationId)['base_url'];
        $account = app(GlpiClientFactory::class)->for($organizationId)->account();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl) . ($account['version'] !== null ? ' · ' . $account['version'] : ''),
            'source_url' => $baseUrl,
            'account_id' => $account['id'],
            'account_name' => $account['name'],
        ];
    }

    protected function providerName(): string {
        return 'GLPI';
    }

    protected function sourceNoun(): string {
        return (string) __('glpi::glpi.source');
    }

    protected function pluginId(): string {
        return GlpiPlugin::ID;
    }

    protected function isConfigured(): bool {
        return GlpiConfig::isConfigured();
    }
}
