<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud;

use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Nextcloud\Services\GuzzleNextcloudTransportFactory;
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 080 MVP-382 / Feature 017 MVP-383).
 * Bindet die WebDAV-Transport-Factory (Tests ersetzen sie durch eine Variante
 * mit gemocktem Guzzle-Client ohne HTTP) und registriert Config + Routen.
 */
class NextcloudServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return NextcloudPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(NextcloudTransportFactory::class, GuzzleNextcloudTransportFactory::class);
    }
}
