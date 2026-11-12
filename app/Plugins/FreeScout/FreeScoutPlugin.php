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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer};
use App\Plugins\FreeScout\Api\FreeScoutClientFactory;
use App\Plugins\FreeScout\Models\FreeScoutConnection;
use App\Plugins\FreeScout\Services\FreeScoutImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};

/**
 * FreeScout-Aufgabenimport (Feature 174, Phase 158): Konversationen ausgewählter FreeScout-Postfächer werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class FreeScoutPlugin extends AbstractPlugin implements \App\Plugins\Contracts\WritesDataDomains, LinksExternalRecords, SlotRenderer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const string ID = 'freescout';

    public const string SERVICE_PROVIDER = FreeScoutServiceProvider::class;

    public const string EXT_TYPE_CONVERSATION = 'conversation';

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
        return SelfHostedSettings::schema(self::ID, [SelfHostedSettings::field(self::ID, 'api_key', 'password')]);
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.freescout.index', 'label' => 'FreeScout', 'icon' => 'support_agent'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return FreeScoutConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return FreeScoutConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(FreeScoutClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(FreeScoutImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_CONVERSATION;
    }

    /** Bereiche der Datenführerschaft, in die das Plugin schreibt (MVP-1592). */
    public function dataDomains(): array {
        return [\App\Enums\Integration\DataDomain::Tasks];
    }
}
