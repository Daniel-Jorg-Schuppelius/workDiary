<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Fritzbox;

use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (geladen vom Core-PluginServiceProvider).
 * Registriert den Import-Service; Routen/Views/Config lädt die Basis nach
 * Konvention.
 */
class FritzboxServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return FritzboxPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(FritzboxImportService::class, fn (): FritzboxImportService => new FritzboxImportService);
    }
}
