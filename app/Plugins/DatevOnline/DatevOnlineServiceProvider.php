<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline;

use App\Plugins\DatevOnline\Console\DatevOnlineSyncCommand;
use App\Plugins\Support\PluginServiceProviderBase;

class DatevOnlineServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return DatevOnlinePlugin::ID;
    }

    protected function bootPlugin(): void {
        $this->commands([DatevOnlineSyncCommand::class]);
    }
}
