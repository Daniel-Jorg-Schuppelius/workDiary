<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Http\Controllers;

use App\Plugins\Jira\Api\{JiraClientFactory, JiraOAuthGrant};
use App\Plugins\Jira\{JiraConfig, JiraPlugin};
use App\Plugins\Jira\Models\{JiraConnection, JiraProjectLink};
use App\Plugins\Jira\Services\JiraImportService;
use App\Plugins\Support\PluginOAuthGrant;
use App\Plugins\Support\TaskSync\TaskImportAdminController;

/**
 * Jira-Verbindungsseite (MVP-1305/1311): Atlassian 3LO ohne PKCE (der
 * dokumentierte Flow), `audience` und `prompt=consent`; nach dem Consent die
 * zugänglichen Sites laden, eine davon ausdrücklich wählen.
 *
 * @extends TaskImportAdminController<JiraProjectLink, JiraConnection>
 */
class JiraAdminController extends TaskImportAdminController {
    protected function importService(): JiraImportService {
        return app(JiraImportService::class);
    }

    protected function linkModel(): string {
        return JiraProjectLink::class;
    }

    protected function remoteProjects(int $organizationId): array {
        return app(JiraClientFactory::class)->for($organizationId)->projects();
    }

    protected function consentContext(int $organizationId): array {
        return ['sources' => app(JiraClientFactory::class)->for($organizationId)->accessibleResources(), 'account_id' => null, 'account_name' => null];
    }

    protected function providerName(): string {
        return 'Jira';
    }

    protected function sourceNoun(): string {
        return (string) __('jira::jira.source');
    }

    protected function oauth(): PluginOAuthGrant {
        return app(JiraOAuthGrant::class);
    }

    protected function isConfigured(): bool {
        return JiraConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return JiraConnection::class;
    }

    protected function pluginId(): string {
        return JiraPlugin::ID;
    }

    protected function usesPkce(): bool {
        return false;
    }

    /** @return array<string, string> */
    protected function extraAuthorizeParams(): array {
        return ['audience' => 'api.atlassian.com', 'prompt' => 'consent'];
    }
}
