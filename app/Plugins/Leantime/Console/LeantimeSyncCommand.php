<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime\Console;

use App\Plugins\Leantime\LeantimePlugin;
use App\Plugins\Leantime\Services\LeantimeImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * Leantime-Abgleich je Organisation (Scheduler `leantime.sync` alle 15 Minuten, `leantime.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Leantime\Models\LeantimeProjectLink, \App\Plugins\Leantime\Models\LeantimeConnection>
 */
class LeantimeSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'leantime:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller To-dos der zugeordneten Quellen}';

    protected $description = 'Importiert To-dos der zugeordneten Leantime-Quellen als Aufgaben.';

    protected function pluginId(): string {
        return LeantimePlugin::ID;
    }

    protected function importService(): LeantimeImportService {
        return app(LeantimeImportService::class);
    }
}
