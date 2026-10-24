<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Gitea\Api\GiteaClientFactory;
use App\Plugins\Gitea\Models\GiteaConnection;
use App\Plugins\Gitea\Services\GiteaImportService;
use App\Plugins\Support\ApiHealthProbe;

/**
 * Gitea-/Forgejo-Aufgabenimport (Feature 171, Phase 158): Issues
 * ausgewählter Repositories einer selbst gehosteten Instanz werden als
 * Aufgaben in WorkDiary-Projekte übernommen, nur lesend. Forgejo nutzt
 * dieselbe API und dasselbe Plugin.
 */
class GiteaPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'gitea';

    public const SERVICE_PROVIDER = GiteaServiceProvider::class;

    public const EXT_TYPE_ISSUE = 'issue';

    public function name(): string {
        return 'Gitea/Forgejo';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('gitea::gitea.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('gitea::gitea.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('gitea::gitea.settings.base_url_help')],
            ['key' => 'api_token', 'label' => __('gitea::gitea.settings.api_token'), 'type' => 'password', 'required' => true, 'help' => __('gitea::gitea.settings.api_token_help')],
            ['key' => 'allow_private_network', 'label' => __('gitea::gitea.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('gitea::gitea.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(GiteaImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.gitea.index', 'label' => 'Gitea/Forgejo', 'icon' => 'code'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! GiteaConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('gitea::gitea.health.not_configured'), 'not_configured');
        }
        $connection = GiteaConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof GiteaConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('gitea::gitea.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe('Gitea/Forgejo', fn () => app(GiteaClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(GiteaImportService::class)->sourceUrl($reference) : null;
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

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('gitea::gitea.task_link')])->render() : null;
    }
}
