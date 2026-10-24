<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea\Console;

use App\Plugins\Gitea\GiteaPlugin;
use App\Plugins\Gitea\Services\GiteaImportService;
use App\Plugins\Support\TaskSync\TaskImportSyncCommand;

/**
 * Gitea-/Forgejo-Abgleich je Organisation (Scheduler `gitea.sync` alle 15 Minuten, `gitea.full_sync` täglich).
 *
 * @extends TaskImportSyncCommand<\App\Plugins\Gitea\Models\GiteaProjectLink, \App\Plugins\Gitea\Models\GiteaConnection>
 */
class GiteaSyncCommand extends TaskImportSyncCommand {
    protected $signature = 'gitea:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Issues der zugeordneten Repositories}';

    protected $description = 'Importiert Issues der zugeordneten Gitea-/Forgejo-Repositories als Aufgaben.';

    protected function pluginId(): string {
        return GiteaPlugin::ID;
    }

    protected function importService(): GiteaImportService {
        return app(GiteaImportService::class);
    }
}
