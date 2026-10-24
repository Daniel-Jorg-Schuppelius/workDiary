<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime;

use App\Plugins\Support\PluginServiceProviderBase;

class LeantimeServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return LeantimePlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([Console\LeantimeSyncCommand::class]);
    }
}
