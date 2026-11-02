<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kanboard;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Kanboard\Api\KanboardClientFactory;
use App\Plugins\Kanboard\Models\KanboardConnection;
use App\Plugins\Kanboard\Services\KanboardImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};

/**
 * Kanboard-Aufgabenimport (Feature 173, Phase 158): Aufgaben ausgewählter Kanboard-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class KanboardPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const ID = 'kanboard';

    public const SERVICE_PROVIDER = KanboardServiceProvider::class;

    public const EXT_TYPE_TASK = 'task';

    public function name(): string {
        return 'Kanboard';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('kanboard::kanboard.plugin.description');
    }

    public function settingsSchema(): array {
        return SelfHostedSettings::schema(self::ID, [
            SelfHostedSettings::field(self::ID, 'username', required: false),
            SelfHostedSettings::field(self::ID, 'api_token', 'password'),
        ]);
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function syncTasks(Organization $organization): array {
        return app(KanboardImportService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.kanboard.index', 'label' => 'Kanboard', 'icon' => 'view_kanban'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return KanboardConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return KanboardConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(KanboardClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(KanboardImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_TASK;
    }
}
