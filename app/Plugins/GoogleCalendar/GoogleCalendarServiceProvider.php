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
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (MVP-328, Bauturbo A8). Registriert
 * Config-Defaults, Routen, Views und den Publish-Command;
 * {@see GoogleCalendarOAuth} ist Singleton — Tests ersetzen ihn durch eine
 * Variante mit Guzzle-MockHandler (Todoist-Muster).
 */
class GoogleCalendarServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return GoogleCalendarPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(GoogleCalendarOAuth::class, fn(): GoogleCalendarOAuth => new GoogleCalendarOAuth());

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GoogleCalendarPublishCommand::class,
                Console\GoogleCalendarImportCommand::class,
            ]);
        }
    }
}
