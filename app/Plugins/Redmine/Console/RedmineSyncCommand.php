<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine\Console;

use App\Plugins\Redmine\RedminePlugin;
use App\Plugins\Redmine\Services\RedmineImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * Redmine-Abgleich je Organisation (Issues und, je Zuordnung eingeschaltet, Zeiteinträge; Scheduler `redmine.sync` alle 15 Minuten, `redmine.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Redmine\Models\RedmineProjectLink, \App\Plugins\Redmine\Models\RedmineConnection>
 */
class RedmineSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'redmine:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Issues der zugeordneten Projekte}';

    protected $description = 'Importiert Issues und Zeiteinträge der zugeordneten Redmine-Projekte.';

    protected function pluginId(): string {
        return RedminePlugin::ID;
    }

    protected function importService(): RedmineImportService {
        return app(RedmineImportService::class);
    }
}
