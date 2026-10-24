<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana;

use App\Plugins\Asana\Api\AsanaOAuthGrant;
use App\Plugins\Support\PluginServiceProviderBase;

/** Bootet den Asana-Import; der Grant ist Singleton, damit Tests den Token-Endpunkt ersetzen können. */
class AsanaServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return AsanaPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(AsanaOAuthGrant::class, static fn (): AsanaOAuthGrant => new AsanaOAuthGrant());
        $this->commands([Console\AsanaSyncCommand::class]);
    }
}
