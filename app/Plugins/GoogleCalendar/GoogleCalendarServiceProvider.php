<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar;

use App\Plugins\GoogleCalendar\Api\GoogleCalendarOAuth;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (MVP-328, Bauturbo A8). Registriert
 * Config-Defaults, Routen, Views und den Publish-Command;
 * {@see GoogleCalendarOAuth} ist Singleton — Tests ersetzen ihn durch eine
 * Variante mit Guzzle-MockHandler (Todoist-Muster).
 */
class GoogleCalendarServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . GoogleCalendarPlugin::ID);

        $this->app->singleton(GoogleCalendarOAuth::class, fn(): GoogleCalendarOAuth => new GoogleCalendarOAuth());

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GoogleCalendarPublishCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'google_calendar');
    }
}
