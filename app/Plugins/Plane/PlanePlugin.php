<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Plane\Api\PlaneClientFactory;
use App\Plugins\Plane\Models\PlaneConnection;
use App\Plugins\Plane\Services\PlaneImportService;
use App\Plugins\Support\ApiHealthProbe;

/**
 * Plane-Aufgabenimport (Feature 173, Phase 158): Arbeitspakete ausgewählter Plane-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class PlanePlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'plane';

    public const SERVICE_PROVIDER = PlaneServiceProvider::class;

    public const EXT_TYPE_WORK_ITEM = 'work_item';

    public function name(): string {
        return 'Plane';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('plane::plane.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('plane::plane.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('plane::plane.settings.base_url_help')],
            ['key' => 'workspace_slug', 'label' => __('plane::plane.settings.workspace_slug'), 'type' => 'text', 'required' => true, 'help' => __('plane::plane.settings.workspace_slug_help')],
            ['key' => 'api_key', 'label' => __('plane::plane.settings.api_key'), 'type' => 'password', 'required' => true, 'help' => __('plane::plane.settings.api_key_help')],
            ['key' => 'allow_private_network', 'label' => __('plane::plane.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('plane::plane.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(PlaneImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.plane.index', 'label' => 'Plane', 'icon' => 'view_timeline'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! PlaneConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('plane::plane.health.not_configured'), 'not_configured');
        }
        $connection = PlaneConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof PlaneConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('plane::plane.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(PlaneClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(PlaneImportService::class)->sourceUrl($reference) : null;
    }

    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'task-dialog.links' || ! $context instanceof Task || ! $this->isEnabled()) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($context->organization_id, self::ID, self::EXT_TYPE_WORK_ITEM)
            ->forReferenceable($context)
            ->first();
        $url = $reference !== null ? $this->externalRecordUrl($reference) : null;

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('plane::plane.task_link')])->render() : null;
    }
}
