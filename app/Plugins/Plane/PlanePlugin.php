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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Plane\Api\PlaneClientFactory;
use App\Plugins\Plane\Models\PlaneConnection;
use App\Plugins\Plane\Services\PlaneImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};

/**
 * Plane-Aufgabenimport (Feature 173, Phase 158): Arbeitspakete ausgewählter Plane-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class PlanePlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const string ID = 'plane';

    public const string SERVICE_PROVIDER = PlaneServiceProvider::class;

    public const string EXT_TYPE_WORK_ITEM = 'work_item';

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
        return SelfHostedSettings::schema(self::ID, [
            SelfHostedSettings::field(self::ID, 'workspace_slug'),
            SelfHostedSettings::field(self::ID, 'api_key', 'password'),
        ]);
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

    protected function healthConfigured(int $organizationId): bool {
        return PlaneConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return PlaneConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(PlaneClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(PlaneImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_WORK_ITEM;
    }
}
