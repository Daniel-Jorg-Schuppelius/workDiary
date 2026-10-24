<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Taiga\Console;

use App\Plugins\Support\TaskSync\TaskImportSyncCommand;
use App\Plugins\Taiga\Services\TaigaImportService;
use App\Plugins\Taiga\TaigaPlugin;

/**
 * Taiga-Abgleich je Organisation (Scheduler `taiga.sync` alle 15 Minuten, `taiga.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Taiga\Models\TaigaProjectLink, \App\Plugins\Taiga\Models\TaigaConnection>
 */
class TaigaSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'taiga:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Userstories der zugeordneten Quellen}';

    protected $description = 'Importiert Userstories der zugeordneten Taiga-Quellen als Aufgaben.';

    protected function pluginId(): string {
        return TaigaPlugin::ID;
    }

    protected function importService(): TaigaImportService {
        return app(TaigaImportService::class);
    }
}
