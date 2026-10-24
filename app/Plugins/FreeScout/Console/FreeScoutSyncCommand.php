<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout\Console;

use App\Plugins\FreeScout\FreeScoutPlugin;
use App\Plugins\FreeScout\Services\FreeScoutImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * FreeScout-Abgleich je Organisation (Scheduler `freescout.sync` alle 15 Minuten, `freescout.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\FreeScout\Models\FreeScoutProjectLink, \App\Plugins\FreeScout\Models\FreeScoutConnection>
 */
class FreeScoutSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'freescout:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Konversationen der zugeordneten Quellen}';

    protected $description = 'Importiert Konversationen der zugeordneten FreeScout-Quellen als Aufgaben.';

    protected function pluginId(): string {
        return FreeScoutPlugin::ID;
    }

    protected function importService(): FreeScoutImportService {
        return app(FreeScoutImportService::class);
    }
}
