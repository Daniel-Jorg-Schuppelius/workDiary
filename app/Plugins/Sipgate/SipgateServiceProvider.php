<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SipgateServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sipgate;

use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 147): hängt `config.php` unter
 * `plugins.sipgate` ein — mehr braucht das Gateway nicht.
 */
class SipgateServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return SipgatePlugin::ID;
    }
}
