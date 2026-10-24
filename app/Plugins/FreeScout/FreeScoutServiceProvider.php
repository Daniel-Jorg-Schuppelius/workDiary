<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout;

use App\Plugins\Support\PluginServiceProviderBase;

class FreeScoutServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return FreeScoutPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([Console\FreeScoutSyncCommand::class]);
    }
}
