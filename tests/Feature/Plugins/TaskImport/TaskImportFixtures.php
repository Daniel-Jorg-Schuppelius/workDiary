<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportFixtures.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use App\Enums\Integration\TaskImportLinkStatus;
use App\Models\Platform\{Organization, PluginSetting};
use App\Models\Project\Project;
use App\Plugins\Asana\Api\AsanaOAuthGrant;
use App\Plugins\Asana\Models\{AsanaConnection, AsanaProjectLink};
use App\Plugins\Jira\Api\JiraOAuthGrant;
use App\Plugins\Jira\Models\{JiraConnection, JiraProjectLink};
use App\Plugins\Support\OAuthConnectionStatus;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as Psr7Response;

/** Gemeinsame Bausteine der Jira-/Asana-Tests (Phase 157): Verbindung, Zuordnung, Antwortformen, Token-Endpunkt. */
trait TaskImportFixtures {
    public const JIRA_CLOUD = 'a1b2c3d4-0000-4000-8000-000000000001';

    public const JIRA_SITE = 'https://muster.atlassian.net';

    public const ASANA_WORKSPACE = '1200000000000001';

    /** @var list<\Psr\Http\Message\RequestInterface> */
    protected array $tokenRequests = [];

    protected function configureTaskImportApps(): void {
        config([
            'plugins.jira.client_id' => 'jira-client',
            'plugins.jira.client_secret' => 'jira-secret',
            'plugins.asana.client_id' => 'asana-client',
            'plugins.asana.client_secret' => 'asana-secret',
        ]);
    }

    protected function enableTaskImportPlugin(string $pluginId, ?Organization $organization = null): void {
        PluginSetting::query()->updateOrCreate(
            ['organization_id' => ($organization ?? $this->organization)->id, 'plugin_id' => $pluginId],
            ['enabled' => true, 'settings' => []],
        );
    }

    /** @param array<string, mixed> $attributes */
    protected function jiraConnection(array $attributes = [], ?Organization $organization = null): JiraConnection {
        return JiraConnection::query()->forceCreate($attributes + [
            'organization_id' => ($organization ?? $this->organization)->id,
            'source_id' => self::JIRA_CLOUD,
            'source_name' => 'Muster GmbH',
            'source_url' => self::JIRA_SITE,
            'available_sources' => [['id' => self::JIRA_CLOUD, 'name' => 'Muster GmbH', 'url' => self::JIRA_SITE]],
            'access_token' => 'jira-access',
            'refresh_token' => 'jira-refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => 'read:jira-work offline_access',
            'status' => OAuthConnectionStatus::Active,
            'connected_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    protected function asanaConnection(array $attributes = [], ?Organization $organization = null): AsanaConnection {
        return AsanaConnection::query()->forceCreate($attributes + [
            'organization_id' => ($organization ?? $this->organization)->id,
            'source_id' => self::ASANA_WORKSPACE,
            'source_name' => 'Muster Workspace',
            'available_sources' => [['id' => self::ASANA_WORKSPACE, 'name' => 'Muster Workspace', 'url' => null]],
            'account_id' => '900001',
            'account_name' => 'Erika Muster',
            'access_token' => 'asana-access',
            'refresh_token' => 'asana-refresh',
            'token_expires_at' => now()->addHour(),
            'status' => OAuthConnectionStatus::Active,
            'connected_at' => now(),
        ]);
    }

    protected function jiraLink(Project $project, string $sourceProjectId = '10000', bool $confirmed = true): JiraProjectLink {
        return JiraProjectLink::query()->forceCreate([
            'organization_id' => $project->organization_id,
            'source_project_id' => $sourceProjectId,
            'source_project_key' => 'MUS',
            'source_project_name' => 'Musterprojekt',
            'project_id' => $project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    protected function asanaLink(Project $project, string $sourceProjectId = '1300000000000001', bool $confirmed = true): AsanaProjectLink {
        return AsanaProjectLink::query()->forceCreate([
            'organization_id' => $project->organization_id,
            'source_project_id' => $sourceProjectId,
            'source_project_name' => 'Asana-Projekt ' . $sourceProjectId,
            'project_id' => $project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function jiraIssue(string $id, string $key, string $summary, string $category = 'new', array $fields = [], string $projectId = '10000'): array {
        return [
            'id' => $id,
            'key' => $key,
            'fields' => $fields + [
                'summary' => $summary,
                'description' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Beschreibung ' . $key]]],
                ]],
                'status' => ['name' => 'Lokalisiert', 'statusCategory' => ['key' => $category]],
                'duedate' => null,
                'project' => ['id' => $projectId, 'key' => 'MUS', 'name' => 'Musterprojekt'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    protected function jiraPage(array $issues, ?string $next = null): array {
        return ['issues' => $issues, 'isLast' => $next === null] + ($next !== null ? ['nextPageToken' => $next] : []);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function asanaTask(string $gid, string $name, bool $completed = false, array $overrides = [], string $projectGid = '1300000000000001'): array {
        return $overrides + [
            'gid' => $gid,
            'name' => $name,
            'notes' => 'Notiz ' . $name,
            'completed' => $completed,
            'due_on' => null,
            'due_at' => null,
            'permalink_url' => 'https://app.asana.com/0/' . $projectGid . '/' . $gid,
            'workspace' => ['gid' => self::ASANA_WORKSPACE],
            'memberships' => [['project' => ['gid' => $projectGid]]],
        ];
    }

    /**
     * Token-Endpunkt des Grants ersetzen; die Anfragen landen in $tokenRequests.
     *
     * @param  class-string<JiraOAuthGrant|AsanaOAuthGrant>  $grant
     * @param  list<Psr7Response>  $responses
     */
    protected function fakeTokenEndpoint(string $grant, array $responses): void {
        $this->tokenRequests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::mapRequest(function ($request) {
            $this->tokenRequests[] = $request;

            return $request;
        }));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $this->app->singleton($grant, static fn () => new $grant($guzzle));
    }

    /** @param array<string, mixed> $body */
    protected static function tokenResponse(array $body = [], int $status = 200): Psr7Response {
        return new Psr7Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body + ($status === 200 ? [
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ] : [])));
    }

    protected function jiraSearchUrl(): string {
        return 'https://api.atlassian.com/ex/jira/' . self::JIRA_CLOUD . '/rest/api/3/search/jql*';
    }

    protected function asanaTasksUrl(string $projectGid = '1300000000000001'): string {
        return 'https://app.asana.com/api/1.0/projects/' . $projectGid . '/tasks*';
    }
}
