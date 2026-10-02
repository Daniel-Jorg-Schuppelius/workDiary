<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SumUpServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SumUp;

use App\Plugins\Support\PluginServiceProviderBase;

class SumUpServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return SumUpPlugin::ID;
    }
}
