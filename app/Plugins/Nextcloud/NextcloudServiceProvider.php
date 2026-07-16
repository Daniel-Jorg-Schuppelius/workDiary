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
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 080 MVP-382 / Feature 017 MVP-383).
 * Bindet die WebDAV-Transport-Factory (Tests ersetzen sie durch eine Variante
 * mit gemocktem Guzzle-Client ohne HTTP) und registriert Config + Routen.
 */
class NextcloudServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . NextcloudPlugin::ID);

        $this->app->singleton(NextcloudTransportFactory::class, GuzzleNextcloudTransportFactory::class);
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }
}
