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

use App\Plugins\Lexoffice\LexofficePhoneContactSource;
use App\Plugins\Msgraph\MsgraphPhoneContactSource;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Contacts\ExternalPhoneContactDirectory;

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
        $this->app->scoped(LexofficePhoneContactSource::class);
        $this->app->scoped(MsgraphPhoneContactSource::class);
        $this->app->tag([
            LexofficePhoneContactSource::class,
            MsgraphPhoneContactSource::class,
        ], 'external-phone-contact-sources');

        $this->app->scoped(
            ExternalPhoneContactDirectory::class,
            fn ($app): ExternalPhoneContactDirectory => new ExternalPhoneContactDirectory($app->tagged('external-phone-contact-sources')),
        );
        $this->app->scoped(
            FritzboxImportService::class,
            fn ($app): FritzboxImportService => new FritzboxImportService($app->make(ExternalPhoneContactDirectory::class)),
        );
    }
}
