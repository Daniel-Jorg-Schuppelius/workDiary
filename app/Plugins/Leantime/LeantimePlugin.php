<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Leantime\Api\LeantimeClientFactory;
use App\Plugins\Leantime\Models\LeantimeConnection;
use App\Plugins\Leantime\Services\LeantimeImportService;
use App\Plugins\Support\ApiHealthProbe;

/**
 * Leantime-Aufgabenimport (Feature 173, Phase 158): To-dos ausgewählter Leantime-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class LeantimePlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'leantime';

    public const SERVICE_PROVIDER = LeantimeServiceProvider::class;

    public const EXT_TYPE_TICKET = 'ticket';

    public function name(): string {
        return 'Leantime';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('leantime::leantime.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('leantime::leantime.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('leantime::leantime.settings.base_url_help')],
            ['key' => 'api_key', 'label' => __('leantime::leantime.settings.api_key'), 'type' => 'password', 'required' => true, 'help' => __('leantime::leantime.settings.api_key_help')],
            ['key' => 'allow_private_network', 'label' => __('leantime::leantime.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('leantime::leantime.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(LeantimeImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.leantime.index', 'label' => 'Leantime', 'icon' => 'rocket_launch'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! LeantimeConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('leantime::leantime.health.not_configured'), 'not_configured');
        }
        $connection = LeantimeConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof LeantimeConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('leantime::leantime.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(LeantimeClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(LeantimeImportService::class)->sourceUrl($reference) : null;
    }

    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'task-dialog.links' || ! $context instanceof Task || ! $this->isEnabled()) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($context->organization_id, self::ID, self::EXT_TYPE_TICKET)
            ->forReferenceable($context)
            ->first();
        $url = $reference !== null ? $this->externalRecordUrl($reference) : null;

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('leantime::leantime.task_link')])->render() : null;
    }
}
