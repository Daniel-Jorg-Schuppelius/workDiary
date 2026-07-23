<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly;

use App\Plugins\Calendly\Console\CalendlyBackfillCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 095). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald CalendlyPlugin in
 * der Registry steht. Liefert Config-Defaults, lädt Routes + Views und stellt
 * den Backfill-Command bereit.
 */
class CalendlyServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . CalendlyPlugin::ID);
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'calendly');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CalendlyBackfillCommand::class,
            ]);
        }
    }
}
