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
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 080, MVP-355): Config-Defaults +
 * Routen; {@see GoogleDriveOAuth} ist Singleton (Test-Austauschpunkt).
 */
class GoogleDriveServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . GoogleDrivePlugin::ID);

        $this->app->singleton(GoogleDriveOAuth::class, fn (): GoogleDriveOAuth => new GoogleDriveOAuth());
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }
}
