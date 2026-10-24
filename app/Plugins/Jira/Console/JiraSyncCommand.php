<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Console;

use App\Plugins\Jira\JiraPlugin;
use App\Plugins\Jira\Services\JiraImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * Jira-Abgleich je Organisation (Scheduler `jira.sync` alle 15 Minuten, `jira.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Jira\Models\JiraProjectLink, \App\Plugins\Jira\Models\JiraConnection>
 */
class JiraSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'jira:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Vorgänge der zugeordneten Projekte}';

    protected $description = 'Importiert Vorgänge der zugeordneten Jira-Projekte als Aufgaben.';

    protected function pluginId(): string {
        return JiraPlugin::ID;
    }

    protected function importService(): JiraImportService {
        return app(JiraImportService::class);
    }
}
