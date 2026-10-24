<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedminePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer, TimeImporter};
use App\Plugins\Redmine\Api\RedmineClientFactory;
use App\Plugins\Redmine\Models\RedmineConnection;
use App\Plugins\Redmine\Services\RedmineImportService;
use App\Plugins\Support\ApiHealthProbe;

/**
 * Redmine-Aufgaben- und Zeitimport (Feature 172, Phase 158): Issues
 * ausgewählter Projekte werden Aufgaben, Zeiteinträge auf Wunsch Zeitbuchungen
 * im Zielprojekt — nur lesend, ohne Rückexport.
 */
class RedminePlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer, TimeImporter {
    public const ID = 'redmine';

    public const SERVICE_PROVIDER = RedmineServiceProvider::class;

    public const EXT_TYPE_ISSUE = 'issue';

    public function name(): string {
        return 'Redmine';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('redmine::redmine.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('redmine::redmine.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('redmine::redmine.settings.base_url_help')],
            ['key' => 'api_key', 'label' => __('redmine::redmine.settings.api_key'), 'type' => 'password', 'required' => true, 'help' => __('redmine::redmine.settings.api_key_help')],
            ['key' => 'allow_private_network', 'label' => __('redmine::redmine.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('redmine::redmine.settings.allow_private_network_help')],
            ['key' => 'default_billable', 'label' => __('redmine::redmine.settings.default_billable'), 'type' => 'boolean', 'default' => true, 'help' => __('redmine::redmine.settings.default_billable_help')],
            ['key' => 'time_window_days', 'label' => __('redmine::redmine.settings.time_window_days'), 'type' => 'number', 'default' => 30, 'help' => __('redmine::redmine.settings.time_window_days_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync, PluginCapability::TimeImport];
    }

    public function syncTasks(Organization $organization): array {
        return app(RedmineImportService::class)->run($organization);
    }

    public function importTimeEntries(Organization $organization): array {
        return app(RedmineImportService::class)->importTimes($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.redmine.index', 'label' => 'Redmine', 'icon' => 'bug_report'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! RedmineConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('redmine::redmine.health.not_configured'), 'not_configured');
        }
        $connection = RedmineConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof RedmineConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('redmine::redmine.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe('Redmine', fn () => app(RedmineClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(RedmineImportService::class)->sourceUrl($reference) : null;
    }

    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'task-dialog.links' || ! $context instanceof Task || ! $this->isEnabled()) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($context->organization_id, self::ID, self::EXT_TYPE_ISSUE)
            ->forReferenceable($context)
            ->first();
        $url = $reference !== null ? $this->externalRecordUrl($reference) : null;

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('redmine::redmine.task_link')])->render() : null;
    }
}
