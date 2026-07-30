<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive;

use App\Plugins\GoogleDrive\Api\GoogleDriveOAuth;
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 080, MVP-355): Config-Defaults +
 * Routen; {@see GoogleDriveOAuth} ist Singleton (Test-Austauschpunkt).
 */
class GoogleDriveServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return GoogleDrivePlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(GoogleDriveOAuth::class, fn (): GoogleDriveOAuth => new GoogleDriveOAuth());
    }
}
