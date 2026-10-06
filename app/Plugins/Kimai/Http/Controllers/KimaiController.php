<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kimai\Http\Controllers;

use App\Plugins\Kimai\{KimaiConfig, KimaiPlugin};
use App\Plugins\Kimai\Services\{KimaiExportService, KimaiImportService};
use App\Plugins\Support\AbstractTimeEntryPushService;
use App\Plugins\Support\TimeTracking\{CsvAndApiTimeImporter, TimeImportAdminController};

/** Kimai: Timesheet-CSV, Import über die REST-API, Rückbuchung als Kimai-Timesheets. */
class KimaiController extends TimeImportAdminController {
    protected function pluginId(): string {
        return KimaiPlugin::ID;
    }

    protected function view(): string {
        return 'kimai::admin.import';
    }

    protected function config(?int $organizationId): array {
        return KimaiConfig::resolve($organizationId);
    }

    protected function apiConfigured(array $config): bool {
        return ($config['api_token'] ?? null) !== null && ($config['base_url'] ?? null) !== null;
    }

    protected function importer(): CsvAndApiTimeImporter {
        return app(KimaiImportService::class);
    }

    protected function exporter(): AbstractTimeEntryPushService {
        return app(KimaiExportService::class);
    }

    protected function importedMessage(array $counts, bool $viaApi): string {
        return $viaApi
            ? (string) __('Kimai-API-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', $counts)
            : (string) __('Kimai-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', $counts);
    }

    protected function exportedMessage(array $counts): string {
        return (string) __('Kimai-Export: :pushed gebucht, :skipped übersprungen, :failed fehlgeschlagen.', $counts);
    }
}
