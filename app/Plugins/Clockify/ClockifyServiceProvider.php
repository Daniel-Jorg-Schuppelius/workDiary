<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Clockify;

use App\Plugins\Clockify\Services\ClockifyOutboxDispatcher;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;

/**
 * Plugin-eigener ServiceProvider (geladen vom Core-PluginServiceProvider, sobald
 * ClockifyPlugin in der Registry steht). Registriert den Import-Service, lädt
 * Routen und Views.
 */
class ClockifyServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return ClockifyPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(ClockifyImportService::class, fn (): ClockifyImportService => new ClockifyImportService);
    }

    protected function bootPlugin(): void {
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new ClockifyOutboxDispatcher);
    }
}
