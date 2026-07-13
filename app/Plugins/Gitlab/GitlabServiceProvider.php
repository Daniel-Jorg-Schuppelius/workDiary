<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab;

use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 060, Bauturbo A6). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald GitlabPlugin in
 * der Registry steht: Config-Defaults, Webhook-Route und Polling-Command.
 * Der HTTP-Transport kommt aus der {@see \App\Plugins\Support\PluginHttpFactory}
 * (php-api-toolkit) — Tests ersetzen ihn durch FakePluginHttp.
 */
class GitlabServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . GitlabPlugin::ID);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GitlabSyncCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }
}
