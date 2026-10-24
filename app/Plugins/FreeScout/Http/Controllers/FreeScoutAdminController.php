<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout\Http\Controllers;

use App\Plugins\FreeScout\Api\FreeScoutClientFactory;
use App\Plugins\FreeScout\{FreeScoutConfig, FreeScoutPlugin};
use App\Plugins\FreeScout\Models\{FreeScoutConnection, FreeScoutProjectLink};
use App\Plugins\FreeScout\Services\FreeScoutImportService;
use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};

/**
 * FreeScout-Verbindungsseite (MVP-1336): „Verbindung prüfen“ liest die Instanz
 * aus den Plugin-Einstellungen; Quellen werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<FreeScoutProjectLink, FreeScoutConnection>
 */
class FreeScoutAdminController extends TokenTaskImportAdminController {
    protected function importService(): FreeScoutImportService {
        return app(FreeScoutImportService::class);
    }

    protected function linkModel(): string {
        return FreeScoutProjectLink::class;
    }

    protected function connectionModel(): string {
        return FreeScoutConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(FreeScoutClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) FreeScoutConfig::resolve($organizationId)['base_url'];
        $account = app(FreeScoutClientFactory::class)->for($organizationId)->account();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl) . ($account['version'] !== null ? ' · ' . $account['version'] : ''),
            'source_url' => $baseUrl,
            'account_id' => $account['id'],
            'account_name' => $account['name'],
        ];
    }

    protected function providerName(): string {
        return 'FreeScout';
    }

    protected function sourceNoun(): string {
        return (string) __('freescout::freescout.source');
    }

    protected function pluginId(): string {
        return FreeScoutPlugin::ID;
    }

    protected function isConfigured(): bool {
        return FreeScoutConfig::isConfigured();
    }
}
