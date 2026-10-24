<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja\Console;

use App\Plugins\Support\TaskSync\TaskImportSyncCommand;
use App\Plugins\Vikunja\Services\VikunjaImportService;
use App\Plugins\Vikunja\VikunjaPlugin;

/**
 * Vikunja-Abgleich je Organisation (Scheduler `vikunja.sync` alle 15 Minuten, `vikunja.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Vikunja\Models\VikunjaProjectLink, \App\Plugins\Vikunja\Models\VikunjaConnection>
 */
class VikunjaSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'vikunja:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Aufgaben der zugeordneten Quellen}';

    protected $description = 'Importiert Aufgaben der zugeordneten Vikunja-Quellen als Aufgaben.';

    protected function pluginId(): string {
        return VikunjaPlugin::ID;
    }

    protected function importService(): VikunjaImportService {
        return app(VikunjaImportService::class);
    }
}
