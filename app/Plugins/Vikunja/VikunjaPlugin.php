<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Support\ApiHealthProbe;
use App\Plugins\Vikunja\Api\VikunjaClientFactory;
use App\Plugins\Vikunja\Models\VikunjaConnection;
use App\Plugins\Vikunja\Services\VikunjaImportService;

/**
 * Vikunja-Aufgabenimport (Feature 173, Phase 158): Aufgaben ausgewählter Vikunja-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class VikunjaPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'vikunja';

    public const SERVICE_PROVIDER = VikunjaServiceProvider::class;

    public const EXT_TYPE_TASK = 'task';

    public function name(): string {
        return 'Vikunja';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('vikunja::vikunja.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('vikunja::vikunja.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('vikunja::vikunja.settings.base_url_help')],
            ['key' => 'api_token', 'label' => __('vikunja::vikunja.settings.api_token'), 'type' => 'password', 'required' => true, 'help' => __('vikunja::vikunja.settings.api_token_help')],
            ['key' => 'allow_private_network', 'label' => __('vikunja::vikunja.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('vikunja::vikunja.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(VikunjaImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.vikunja.index', 'label' => 'Vikunja', 'icon' => 'checklist'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! VikunjaConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('vikunja::vikunja.health.not_configured'), 'not_configured');
        }
        $connection = VikunjaConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof VikunjaConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('vikunja::vikunja.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(VikunjaClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(VikunjaImportService::class)->sourceUrl($reference) : null;
    }

    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'task-dialog.links' || ! $context instanceof Task || ! $this->isEnabled()) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($context->organization_id, self::ID, self::EXT_TYPE_TASK)
            ->forReferenceable($context)
            ->first();
        $url = $reference !== null ? $this->externalRecordUrl($reference) : null;

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('vikunja::vikunja.task_link')])->render() : null;
    }
}
