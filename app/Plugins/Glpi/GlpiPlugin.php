<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Glpi\Api\GlpiClientFactory;
use App\Plugins\Glpi\Models\GlpiConnection;
use App\Plugins\Glpi\Services\GlpiImportService;
use App\Plugins\Support\ApiHealthProbe;

/**
 * GLPI-Aufgabenimport (Feature 174, Phase 158): Tickets ausgewählter GLPI-Entitäten werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class GlpiPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'glpi';

    public const SERVICE_PROVIDER = GlpiServiceProvider::class;

    public const EXT_TYPE_TICKET = 'ticket';

    public function name(): string {
        return 'GLPI';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('glpi::glpi.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('glpi::glpi.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('glpi::glpi.settings.base_url_help')],
            ['key' => 'app_token', 'label' => __('glpi::glpi.settings.app_token'), 'type' => 'password', 'help' => __('glpi::glpi.settings.app_token_help')],
            ['key' => 'user_token', 'label' => __('glpi::glpi.settings.user_token'), 'type' => 'password', 'required' => true, 'help' => __('glpi::glpi.settings.user_token_help')],
            ['key' => 'allow_private_network', 'label' => __('glpi::glpi.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('glpi::glpi.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(GlpiImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.glpi.index', 'label' => 'GLPI', 'icon' => 'confirmation_number'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! GlpiConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('glpi::glpi.health.not_configured'), 'not_configured');
        }
        $connection = GlpiConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof GlpiConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('glpi::glpi.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(GlpiClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(GlpiImportService::class)->sourceUrl($reference) : null;
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

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('glpi::glpi.task_link')])->render() : null;
    }
}
