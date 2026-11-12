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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer};
use App\Plugins\Glpi\Api\GlpiClientFactory;
use App\Plugins\Glpi\Models\GlpiConnection;
use App\Plugins\Glpi\Services\GlpiImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};

/**
 * GLPI-Aufgabenimport (Feature 174, Phase 158): Tickets ausgewählter GLPI-Entitäten werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 */
class GlpiPlugin extends AbstractPlugin implements \App\Plugins\Contracts\WritesDataDomains, LinksExternalRecords, SlotRenderer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const string ID = 'glpi';

    public const string SERVICE_PROVIDER = GlpiServiceProvider::class;

    public const string EXT_TYPE_TICKET = 'ticket';

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
        return SelfHostedSettings::schema(self::ID, [
            SelfHostedSettings::field(self::ID, 'app_token', 'password', required: false),
            SelfHostedSettings::field(self::ID, 'user_token', 'password'),
        ]);
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.glpi.index', 'label' => 'GLPI', 'icon' => 'confirmation_number'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return GlpiConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return GlpiConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(GlpiClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(GlpiImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_TICKET;
    }

    /** Bereiche der Datenführerschaft, in die das Plugin schreibt (MVP-1592). */
    public function dataDomains(): array {
        return [\App\Enums\Integration\DataDomain::Tasks];
    }
}
