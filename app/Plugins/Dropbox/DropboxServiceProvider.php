<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox;

use App\Plugins\Dropbox\Api\DropboxOAuth;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 080, MVP-353): Config-Defaults +
 * Routen; {@see DropboxOAuth} ist Singleton — Tests ersetzen ihn durch eine
 * Variante mit Guzzle-MockHandler (Muster GoogleCalendar/Todoist).
 */
class DropboxServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . DropboxPlugin::ID);

        $this->app->singleton(DropboxOAuth::class, fn (): DropboxOAuth => new DropboxOAuth());
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }
}
