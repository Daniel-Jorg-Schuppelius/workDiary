<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Taiga;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Support\ApiHealthProbe;
use App\Plugins\Taiga\Api\TaigaClientFactory;
use App\Plugins\Taiga\Models\TaigaConnection;
use App\Plugins\Taiga\Services\TaigaImportService;

/**
 * Taiga-Aufgabenimport (Feature 173, Phase 158): Userstories ausgewählter Taiga-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class TaigaPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'taiga';

    public const SERVICE_PROVIDER = TaigaServiceProvider::class;

    public const EXT_TYPE_USER_STORY = 'user_story';

    public function name(): string {
        return 'Taiga';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('taiga::taiga.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('taiga::taiga.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('taiga::taiga.settings.base_url_help')],
            ['key' => 'username', 'label' => __('taiga::taiga.settings.username'), 'type' => 'text', 'required' => true, 'help' => __('taiga::taiga.settings.username_help')],
            ['key' => 'password', 'label' => __('taiga::taiga.settings.password'), 'type' => 'password', 'required' => true, 'help' => __('taiga::taiga.settings.password_help')],
            ['key' => 'allow_private_network', 'label' => __('taiga::taiga.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('taiga::taiga.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(TaigaImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.taiga.index', 'label' => 'Taiga', 'icon' => 'view_week'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! TaigaConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('taiga::taiga.health.not_configured'), 'not_configured');
        }
        $connection = TaigaConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof TaigaConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('taiga::taiga.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(TaigaClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(TaigaImportService::class)->sourceUrl($reference) : null;
    }

    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'task-dialog.links' || ! $context instanceof Task || ! $this->isEnabled()) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($context->organization_id, self::ID, self::EXT_TYPE_USER_STORY)
            ->forReferenceable($context)
            ->first();
        $url = $reference !== null ? $this->externalRecordUrl($reference) : null;

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('taiga::taiga.task_link')])->render() : null;
    }
}
