<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Http\Controllers;

use App\Plugins\Asana\Api\{AsanaClientFactory, AsanaOAuthGrant};
use App\Plugins\Asana\{AsanaConfig, AsanaPlugin};
use App\Plugins\Asana\Models\{AsanaConnection, AsanaProjectLink};
use App\Plugins\Asana\Services\AsanaImportService;
use App\Plugins\Support\PluginOAuthGrant;
use App\Plugins\Support\TaskSync\TaskImportAdminController;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Asana-Verbindungsseite (MVP-1307/1311): OAuth mit PKCE, danach Workspace
 * aus dem Konto wählen. Beim Trennen wird das Refresh-Token bei Asana
 * widerrufen, solange es noch gilt.
 *
 * @extends TaskImportAdminController<AsanaProjectLink, AsanaConnection>
 */
class AsanaAdminController extends TaskImportAdminController {
    protected function importService(): AsanaImportService {
        return app(AsanaImportService::class);
    }

    protected function linkModel(): string {
        return AsanaProjectLink::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(AsanaClientFactory::class)->for($organizationId)->projects();
    }

    protected function consentContext(int $organizationId): array {
        $me = app(AsanaClientFactory::class)->for($organizationId)->me();

        return ['sources' => $me['workspaces'], 'account_id' => $me['gid'], 'account_name' => $me['name']];
    }

    protected function providerName(): string {
        return 'Asana';
    }

    protected function sourceNoun(): string {
        return (string) __('asana::asana.source');
    }

    protected function oauth(): PluginOAuthGrant {
        return app(AsanaOAuthGrant::class);
    }

    protected function isConfigured(): bool {
        return AsanaConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return AsanaConnection::class;
    }

    protected function pluginId(): string {
        return AsanaPlugin::ID;
    }

    /** Widerruf ist Bestpraxis, kein Muss: scheitert er, wird lokal trotzdem getrennt. */
    protected function beforeDisconnect(Model $connection): void {
        $token = trim((string) $connection->getAttribute('refresh_token'));
        if ($token === '') {
            return;
        }
        try {
            app(AsanaOAuthGrant::class)->grant()->revokeToken($token, 'refresh_token');
        } catch (Throwable $e) {
            report($e);
        }
    }
}
