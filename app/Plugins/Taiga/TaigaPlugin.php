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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};
use App\Plugins\Taiga\Api\TaigaClientFactory;
use App\Plugins\Taiga\Models\TaigaConnection;
use App\Plugins\Taiga\Services\TaigaImportService;

/**
 * Taiga-Aufgabenimport (Feature 173, Phase 158): Userstories ausgewählter Taiga-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class TaigaPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const string ID = 'taiga';

    public const string SERVICE_PROVIDER = TaigaServiceProvider::class;

    public const string EXT_TYPE_USER_STORY = 'user_story';

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
        return SelfHostedSettings::schema(self::ID, [
            SelfHostedSettings::field(self::ID, 'username'),
            SelfHostedSettings::field(self::ID, 'password', 'password'),
        ]);
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

    protected function healthConfigured(int $organizationId): bool {
        return TaigaConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return TaigaConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(TaigaClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(TaigaImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_USER_STORY;
    }
}
