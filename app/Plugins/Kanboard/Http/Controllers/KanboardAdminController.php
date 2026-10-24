<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kanboard\Http\Controllers;

use App\Plugins\Kanboard\Api\KanboardClientFactory;
use App\Plugins\Kanboard\{KanboardConfig, KanboardPlugin};
use App\Plugins\Kanboard\Models\{KanboardConnection, KanboardProjectLink};
use App\Plugins\Kanboard\Services\KanboardImportService;
use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};

/**
 * Kanboard-Verbindungsseite (MVP-1341): „Verbindung prüfen“ liest die Instanz
 * aus den Plugin-Einstellungen; Quellen werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<KanboardProjectLink, KanboardConnection>
 */
class KanboardAdminController extends TokenTaskImportAdminController {
    protected function importService(): KanboardImportService {
        return app(KanboardImportService::class);
    }

    protected function linkModel(): string {
        return KanboardProjectLink::class;
    }

    protected function connectionModel(): string {
        return KanboardConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(KanboardClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) KanboardConfig::resolve($organizationId)['base_url'];
        $account = app(KanboardClientFactory::class)->for($organizationId)->account();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl) . ($account['version'] !== null ? ' · ' . $account['version'] : ''),
            'source_url' => $baseUrl,
            'account_id' => $account['id'],
            'account_name' => $account['name'],
        ];
    }

    protected function providerName(): string {
        return 'Kanboard';
    }

    protected function sourceNoun(): string {
        return (string) __('kanboard::kanboard.source');
    }

    protected function pluginId(): string {
        return KanboardPlugin::ID;
    }

    protected function isConfigured(): bool {
        return KanboardConfig::isConfigured();
    }
}
