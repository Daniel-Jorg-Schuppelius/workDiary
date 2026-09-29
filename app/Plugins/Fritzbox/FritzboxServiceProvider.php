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
use App\Services\Contacts\ExternalPhoneContactDirectory;
use App\Services\Integration\InboxGroupBookerRegistry;

/**
 * Plugin-eigener ServiceProvider (geladen vom Core-PluginServiceProvider).
 * Registriert den Import-Service; Routen/Views/Config lädt die Basis nach
 * Konvention. Das Rufnummern-Aggregat kommt aus dem Kern
 * ({@see \App\Providers\ContactsServiceProvider}).
 */
class FritzboxServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return FritzboxPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->scoped(
            FritzboxImportService::class,
            fn($app): FritzboxImportService => new FritzboxImportService($app->make(ExternalPhoneContactDirectory::class)),
        );
    }

    protected function bootPlugin(): void {
        // Gruppierte Auflösung der Import-Inbox (MVP-1030).
        $this->app->make(InboxGroupBookerRegistry::class)->register(FritzboxPlugin::ID, FritzboxGroupBooker::class);
    }
}
