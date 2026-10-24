<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime\Http\Controllers;

use App\Plugins\Leantime\Api\LeantimeClientFactory;
use App\Plugins\Leantime\{LeantimeConfig, LeantimePlugin};
use App\Plugins\Leantime\Models\{LeantimeConnection, LeantimeProjectLink};
use App\Plugins\Leantime\Services\LeantimeImportService;
use App\Plugins\Support\TaskSync\{SelfHostedInstance, TokenTaskImportAdminController};

/**
 * Leantime-Verbindungsseite (MVP-1344): „Verbindung prüfen“ liest die Instanz
 * aus den Plugin-Einstellungen; Quellen werden einzeln zugeordnet.
 *
 * @extends TokenTaskImportAdminController<LeantimeProjectLink, LeantimeConnection>
 */
class LeantimeAdminController extends TokenTaskImportAdminController {
    protected function importService(): LeantimeImportService {
        return app(LeantimeImportService::class);
    }

    protected function linkModel(): string {
        return LeantimeProjectLink::class;
    }

    protected function connectionModel(): string {
        return LeantimeConnection::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(LeantimeClientFactory::class)->for($organizationId)->projects();
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) LeantimeConfig::resolve($organizationId)['base_url'];
        $account = app(LeantimeClientFactory::class)->for($organizationId)->account();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl) . ($account['version'] !== null ? ' · ' . $account['version'] : ''),
            'source_url' => $baseUrl,
            'account_id' => $account['id'],
            'account_name' => $account['name'],
        ];
    }

    protected function providerName(): string {
        return 'Leantime';
    }

    protected function sourceNoun(): string {
        return (string) __('leantime::leantime.source');
    }

    protected function pluginId(): string {
        return LeantimePlugin::ID;
    }

    protected function isConfigured(): bool {
        return LeantimeConfig::isConfigured();
    }
}
