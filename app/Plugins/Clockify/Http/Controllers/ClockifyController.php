<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Http\Controllers;

use App\Plugins\Clockify\{ClockifyConfig, ClockifyPlugin};
use App\Plugins\Clockify\Services\{ClockifyExportService, ClockifyImportService};
use App\Plugins\Support\AbstractTimeEntryPushService;
use App\Plugins\Support\TimeTracking\{CsvAndApiTimeImporter, TimeImportAdminController};

/** Clockify: Detailed-Report-CSV, Import über die Reports-API, Übertragung lokaler Zeiten (Spiegelung, Toggl-Muster). */
class ClockifyController extends TimeImportAdminController {
    protected function pluginId(): string {
        return ClockifyPlugin::ID;
    }

    protected function view(): string {
        return 'clockify::admin.import';
    }

    protected function config(?int $organizationId): array {
        return ClockifyConfig::resolve($organizationId);
    }

    protected function apiConfigured(array $config): bool {
        return ($config['api_key'] ?? null) !== null;
    }

    protected function importer(): CsvAndApiTimeImporter {
        return app(ClockifyImportService::class);
    }

    protected function exporter(): AbstractTimeEntryPushService {
        return app(ClockifyExportService::class);
    }

    protected function importedMessage(array $counts, bool $viaApi): string {
        return $viaApi
            ? (string) __('Clockify-API-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', $counts)
            : (string) __('Clockify-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', $counts);
    }

    protected function exportedMessage(array $counts): string {
        return (string) __('Clockify-Übertragung: :pushed übertragen, :skipped übersprungen, :failed fehlgeschlagen.', $counts);
    }
}
