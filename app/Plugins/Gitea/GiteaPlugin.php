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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer};
use App\Plugins\Gitea\Api\GiteaClientFactory;
use App\Plugins\Gitea\Models\GiteaConnection;
use App\Plugins\Gitea\Services\GiteaImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};

/**
 * Gitea-/Forgejo-Aufgabenimport (Feature 171, Phase 158): Issues
 * ausgewählter Repositories einer selbst gehosteten Instanz werden als
 * Aufgaben in WorkDiary-Projekte übernommen, nur lesend. Forgejo nutzt
 * dieselbe API und dasselbe Plugin.
 */
class GiteaPlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer {
    use ChecksConnectionHealth, RendersTaskLink;

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
        return SelfHostedSettings::schema(self::ID, [SelfHostedSettings::field(self::ID, 'api_token', 'password')]);
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

    protected function healthConfigured(int $organizationId): bool {
        return GiteaConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return GiteaConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(GiteaClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(GiteaImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_ISSUE;
    }
}
