<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane\Http\Controllers;

use App\Plugins\Plane\Api\PlaneClientFactory;
use App\Plugins\Plane\Models\{PlaneConnection, PlaneProjectLink};
use App\Plugins\Plane\{PlaneConfig, PlanePlugin};
use App\Plugins\Plane\Services\PlaneImportService;
use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};

/**
 * Plane-Verbindungsseite (MVP-1342): „Verbindung prüfen“ liest die Instanz
 * aus den Plugin-Einstellungen; Quellen werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<PlaneProjectLink, PlaneConnection>
 */
class PlaneAdminController extends TokenTaskImportAdminController {
    protected function importService(): PlaneImportService {
        return app(PlaneImportService::class);
    }

    protected function linkModel(): string {
        return PlaneProjectLink::class;
    }

    protected function connectionModel(): string {
        return PlaneConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(PlaneClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $client = app(PlaneClientFactory::class)->for($organizationId);
        $account = $client->account();
        // Instanz ist der Workspace: ein anderes Kürzel auf demselben Host ist eine andere Quelle.
        $workspaceUrl = $client->workspaceUrl();

        return [
            'source_id' => SelfHostedInstance::key($workspaceUrl),
            'source_name' => SelfHostedInstance::name($workspaceUrl),
            'source_url' => $workspaceUrl,
            'account_id' => $account['id'],
            'account_name' => $account['name'],
        ];
    }

    protected function providerName(): string {
        return 'Plane';
    }

    protected function sourceNoun(): string {
        return (string) __('plane::plane.source');
    }

    protected function pluginId(): string {
        return PlanePlugin::ID;
    }

    protected function isConfigured(): bool {
        return PlaneConfig::isConfigured();
    }
}
