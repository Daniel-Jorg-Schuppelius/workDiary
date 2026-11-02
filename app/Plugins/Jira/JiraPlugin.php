<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Jira\Api\JiraClientFactory;
use App\Plugins\Jira\Models\JiraConnection;
use App\Plugins\Jira\Services\JiraImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink};

/**
 * Jira-Cloud-Aufgabenimport (Feature 169, Phase 157): ausgewählte Projekte
 * einer Site werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 * Eine Verbindung je Organisation über die verteilbare Atlassian-App des
 * Herausgebers; Abgleich alle 15 Minuten, Vollabgleich täglich.
 */
class JiraPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const ID = 'jira';

    public const SERVICE_PROVIDER = JiraServiceProvider::class;

    public const EXT_TYPE_ISSUE = 'issue';

    public function name(): string {
        return 'Jira';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('jira::jira.plugin.description');
    }

    /** Keine Einstellung je Organisation: die App-Registrierung ist installationsweit. */
    public function settingsSchema(): array {
        return [];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(JiraImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.jira.index', 'label' => 'Jira', 'icon' => 'bug_report'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return JiraConfig::isConfigured();
    }

    protected function healthConnectionModel(): string {
        return JiraConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(JiraClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(JiraImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_ISSUE;
    }

    /** Cloud-ID aus den zugänglichen Ressourcen (UUID-Form, sonst nichts in Pfade). */
    public static function isCloudId(string $value): bool {
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $value) === 1;
    }

    /** Site-Adresse: nur https auf *.atlassian.net, ohne Pfad. */
    public static function isSiteUrl(string $url): bool {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && preg_match('/^[a-z0-9-]+\.atlassian\.net$/i', (string) ($parts['host'] ?? '')) === 1
            && array_intersect_key($parts, array_flip(['port', 'user', 'pass', 'query', 'fragment'])) === []
            && in_array($parts['path'] ?? '', ['', '/'], true);
    }
}
