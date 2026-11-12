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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer};
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};
use App\Plugins\Vikunja\Api\VikunjaClientFactory;
use App\Plugins\Vikunja\Models\VikunjaConnection;
use App\Plugins\Vikunja\Services\VikunjaImportService;

/**
 * Vikunja-Aufgabenimport (Feature 173, Phase 158): Aufgaben ausgewählter Vikunja-Projekte werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class VikunjaPlugin extends AbstractPlugin implements \App\Plugins\Contracts\WritesDataDomains, LinksExternalRecords, SlotRenderer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const string ID = 'vikunja';

    public const string SERVICE_PROVIDER = VikunjaServiceProvider::class;

    public const string EXT_TYPE_TASK = 'task';

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
        return SelfHostedSettings::schema(self::ID, [SelfHostedSettings::field(self::ID, 'api_token', 'password')]);
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.vikunja.index', 'label' => 'Vikunja', 'icon' => 'checklist'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return VikunjaConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return VikunjaConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(VikunjaClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(VikunjaImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_TASK;
    }

    /** Bereiche der Datenführerschaft, in die das Plugin schreibt (MVP-1592). */
    public function dataDomains(): array {
        return [\App\Enums\Integration\DataDomain::Tasks];
    }
}
