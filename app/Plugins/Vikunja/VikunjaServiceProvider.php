<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja;

use App\Plugins\Support\PluginServiceProviderBase;

class VikunjaServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return VikunjaPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([Console\VikunjaSyncCommand::class]);
    }
}
