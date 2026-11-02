<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedminePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine;

use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{LinksExternalRecords, PluginCapability, SlotRenderer, TaskSyncer, TimeImporter};
use App\Plugins\Redmine\Api\RedmineClientFactory;
use App\Plugins\Redmine\Models\RedmineConnection;
use App\Plugins\Redmine\Services\RedmineImportService;
use App\Plugins\Support\{ChecksConnectionHealth, RendersTaskLink, SelfHostedSettings};

/**
 * Redmine-Aufgaben- und Zeitimport (Feature 172, Phase 158): Issues
 * ausgewählter Projekte werden Aufgaben, Zeiteinträge auf Wunsch Zeitbuchungen
 * im Zielprojekt — nur lesend, ohne Rückexport.
 */
class RedminePlugin extends AbstractPlugin implements LinksExternalRecords, SlotRenderer, TaskSyncer, TimeImporter {
    use ChecksConnectionHealth, RendersTaskLink;

    public const ID = 'redmine';

    public const SERVICE_PROVIDER = RedmineServiceProvider::class;

    public const EXT_TYPE_ISSUE = 'issue';

    public function name(): string {
        return 'Redmine';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('redmine::redmine.plugin.description');
    }

    public function settingsSchema(): array {
        return SelfHostedSettings::schema(self::ID, [SelfHostedSettings::field(self::ID, 'api_key', 'password')], [
            SelfHostedSettings::field(self::ID, 'default_billable', 'boolean', required: false, default: true),
            SelfHostedSettings::field(self::ID, 'time_window_days', 'number', required: false, default: 30),
        ]);
    }

    public function capabilities(): array {
        return [PluginCapability::TaskSync, PluginCapability::TimeImport];
    }

    public function syncTasks(Organization $organization): array {
        return app(RedmineImportService::class)->run($organization);
    }

    public function importTimeEntries(Organization $organization): array {
        return app(RedmineImportService::class)->importTimes($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.redmine.index', 'label' => 'Redmine', 'icon' => 'bug_report'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return RedmineConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return RedmineConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(RedmineClientFactory::class)->for($organizationId)->probe();
    }

    public function externalRecordUrl(ExternalReference $reference): ?string {
        return $reference->plugin_id === self::ID ? app(RedmineImportService::class)->sourceUrl($reference) : null;
    }

    protected function taskLinkType(): string {
        return self::EXT_TYPE_ISSUE;
    }
}
