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
use App\Plugins\AbstractPlugin;
use App\Plugins\Asana\Api\AsanaClientFactory;
use App\Plugins\Asana\Models\AsanaConnection;
use App\Plugins\Asana\Services\AsanaImportService;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer};
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink};

/**
 * Asana-Aufgabenimport (Feature 169, Phase 157): ausgewählte Projekte eines
 * Workspaces werden als Aufgaben in WorkDiary-Projekte übernommen, nur lesend.
 * Eine Verbindung je Organisation (OAuth mit PKCE); Abgleich alle 15 Minuten,
 * Vollabgleich täglich.
 */
class AsanaPlugin extends AbstractPlugin implements \App\Plugins\Contracts\WritesDataDomains, LinksExternalRecords, SlotRenderer {
    use ChecksConnectionHealth, RendersTaskLink;

    public const string ID = 'asana';

    public const string SERVICE_PROVIDER = AsanaServiceProvider::class;

    public const string EXT_TYPE_TASK = 'task';

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

    public function adminPanel(): ?array {
        return ['route' => 'admin.asana.index', 'label' => 'Asana', 'icon' => 'task_alt'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return AsanaConfig::isConfigured();
    }

    protected function healthConnectionModel(): string {
        return AsanaConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(AsanaClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(AsanaImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_TASK;
    }

    /** Asana-GIDs sind Ziffernfolgen; nur solche gehen in Pfade. */
    public static function isGid(mixed $value): bool {
        return is_string($value) && preg_match('/^\d{1,32}$/', $value) === 1;
    }

    /** Bereiche der Datenführerschaft, in die das Plugin schreibt (MVP-1592). */
    public function dataDomains(): array {
        return [\App\Enums\Integration\DataDomain::Tasks];
    }
}
