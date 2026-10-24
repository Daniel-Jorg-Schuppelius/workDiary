<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane\Console;

use App\Plugins\Plane\PlanePlugin;
use App\Plugins\Plane\Services\PlaneImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * Plane-Abgleich je Organisation (Scheduler `plane.sync` alle 15 Minuten, `plane.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Plane\Models\PlaneProjectLink, \App\Plugins\Plane\Models\PlaneConnection>
 */
class PlaneSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'plane:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Arbeitspakete der zugeordneten Quellen}';

    protected $description = 'Importiert Arbeitspakete der zugeordneten Plane-Quellen als Aufgaben.';

    protected function pluginId(): string {
        return PlanePlugin::ID;
    }

    protected function importService(): PlaneImportService {
        return app(PlaneImportService::class);
    }
}
