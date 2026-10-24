<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi\Console;

use App\Plugins\Glpi\GlpiPlugin;
use App\Plugins\Glpi\Services\GlpiImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * GLPI-Abgleich je Organisation (Scheduler `glpi.sync` alle 15 Minuten, `glpi.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Glpi\Models\GlpiProjectLink, \App\Plugins\Glpi\Models\GlpiConnection>
 */
class GlpiSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'glpi:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Tickets der zugeordneten Quellen}';

    protected $description = 'Importiert Tickets der zugeordneten GLPI-Quellen als Aufgaben.';

    protected function pluginId(): string {
        return GlpiPlugin::ID;
    }

    protected function importService(): GlpiImportService {
        return app(GlpiImportService::class);
    }
}
