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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Leantime\Api\LeantimeClientFactory;
use App\Plugins\Leantime\Models\LeantimeConnection;
use App\Plugins\Leantime\Services\LeantimeImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};

/**
 * Leantime-Aufgabenimport (Feature 173, Phase 158): To-dos ausgewählter Leantime-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class LeantimePlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const string ID = 'leantime';

    public const string SERVICE_PROVIDER = LeantimeServiceProvider::class;

    public const string EXT_TYPE_TICKET = 'ticket';

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
        return SelfHostedSettings::schema(self::ID, [SelfHostedSettings::field(self::ID, 'api_key', 'password')]);
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

    protected function healthConfigured(int $organizationId): bool {
        return LeantimeConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return LeantimeConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(LeantimeClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(LeantimeImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_TICKET;
    }
}
