<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Console;

use App\Plugins\Asana\AsanaPlugin;
use App\Plugins\Asana\Services\AsanaImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * Asana-Abgleich je Organisation (Scheduler `asana.sync` alle 15 Minuten, `asana.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Asana\Models\AsanaProjectLink, \App\Plugins\Asana\Models\AsanaConnection>
 */
class AsanaSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'asana:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Aufgaben der zugeordneten Projekte}';

    protected $description = 'Importiert Aufgaben der zugeordneten Asana-Projekte.';

    protected function pluginId(): string {
        return AsanaPlugin::ID;
    }

    protected function importService(): AsanaImportService {
        return app(AsanaImportService::class);
    }
}
