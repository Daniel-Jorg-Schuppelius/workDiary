<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kanboard\Console;

use App\Plugins\Kanboard\KanboardPlugin;
use App\Plugins\Kanboard\Services\KanboardImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * Kanboard-Abgleich je Organisation (Scheduler `kanboard.sync` alle 15 Minuten, `kanboard.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Kanboard\Models\KanboardProjectLink, \App\Plugins\Kanboard\Models\KanboardConnection>
 */
class KanboardSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'kanboard:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Aufgaben der zugeordneten Quellen}';

    protected $description = 'Importiert Aufgaben der zugeordneten Kanboard-Quellen als Aufgaben.';

    protected function pluginId(): string {
        return KanboardPlugin::ID;
    }

    protected function importService(): KanboardImportService {
        return app(KanboardImportService::class);
    }
}
