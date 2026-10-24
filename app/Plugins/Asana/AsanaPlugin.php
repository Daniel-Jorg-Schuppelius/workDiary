<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Project\Task;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Asana\Api\AsanaClientFactory;
use App\Plugins\Asana\Models\AsanaConnection;
use App\Plugins\Asana\Services\AsanaImportService;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Support\ApiHealthProbe;

/**
 * Asana-Aufgabenimport (Feature 169, Phase 157): ausgewählte Projekte eines
 * Workspaces werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 * Eine Verbindung je Organisation (OAuth mit PKCE); Abgleich alle 15 Minuten,
 * Vollabgleich täglich.
 */
class AsanaPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    public const ID = 'asana';

    public const SERVICE_PROVIDER = AsanaServiceProvider::class;

    public const EXT_TYPE_TASK = 'task';

    public function name(): string {
        return 'Asana';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('asana::asana.plugin.description');
    }

    /** Keine Einstellung je Organisation: die App-Registrierung ist installationsweit. */
    public function settingsSchema(): array {
        return [];
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(AsanaImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.asana.index', 'label' => 'Asana', 'icon' => 'task_alt'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! AsanaConfig::isConfigured()) {
            return PluginHealth::degraded((string) __('asana::asana.health.not_configured'), 'not_configured');
        }
        $connection = AsanaConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof AsanaConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('asana::asana.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe('Asana', fn () => app(AsanaClientFactory::class)->for((int) $organization->id)->probe());
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(AsanaImportService::class)->sourceUrl($reference) : null;
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

        return $url !== null ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __('asana::asana.task_link')])->render() : null;
    }

    /** Asana-GIDs sind Ziffernfolgen; nur solche gehen in Pfade. */
    public static function isGid(mixed $value): bool {
        return is_string($value) && preg_match('/^\d{1,32}$/', $value) === 1;
    }
}
