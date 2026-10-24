<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\FreeScout\Api\FreeScoutClientFactory;
use App\Plugins\FreeScout\Models\FreeScoutConnection;
use App\Plugins\FreeScout\Services\FreeScoutImportService;
use App\Plugins\Support\ApiHealthProbe;

/**
 * FreeScout-Aufgabenimport (Feature 174, Phase 158): Konversationen ausgewählter FreeScout-Postfächer werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class FreeScoutPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'freescout';

    public const SERVICE_PROVIDER = FreeScoutServiceProvider::class;

    public const EXT_TYPE_CONVERSATION = 'conversation';

    public function name(): string {
        return 'FreeScout';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('freescout::freescout.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('freescout::freescout.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('freescout::freescout.settings.base_url_help')],
            ['key' => 'api_key', 'label' => __('freescout::freescout.settings.api_key'), 'type' => 'password', 'required' => true, 'help' => __('freescout::freescout.settings.api_key_help')],
            ['key' => 'allow_private_network', 'label' => __('freescout::freescout.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('freescout::freescout.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(FreeScoutImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.freescout.index', 'label' => 'FreeScout', 'icon' => 'support_agent'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! FreeScoutConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('freescout::freescout.health.not_configured'), 'not_configured');
        }
        $connection = FreeScoutConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof FreeScoutConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('freescout::freescout.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(FreeScoutClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(FreeScoutImportService::class)->sourceUrl($reference) : null;
    }

    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'task-dialog.links' || ! $context instanceof Task || ! $this->isEnabled()) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($context->organization_id, self::ID, self::EXT_TYPE_CONVERSATION)
            ->forReferenceable($context)
            ->first();
        $url = $reference !== null ? $this->externalRecordUrl($reference) : null;

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('freescout::freescout.task_link')])->render() : null;
    }
}
