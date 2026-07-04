<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist;

use App\Models\Task;
use App\Plugins\Todoist\Api\TodoistOAuth;
use App\Plugins\Todoist\Observers\TodoistTaskObserver;
use App\Plugins\Todoist\Services\TodoistOutboxDispatcher;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 055). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald TodoistPlugin
 * in der Registry steht. Registriert Config-Defaults, Routen, Views, den
 * Export-Observer (MVP-114) und den Outbox-Dispatcher an der Registry;
 * {@see TodoistOAuth} ist Singleton — Tests ersetzen ihn durch eine Variante
 * mit Guzzle-MockHandler.
 */
class TodoistServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . TodoistPlugin::ID);

        $this->app->singleton(TodoistOAuth::class, fn (): TodoistOAuth => new TodoistOAuth());

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\TodoistSyncCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'todoist');

        Task::observe(TodoistTaskObserver::class);
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new TodoistOutboxDispatcher());
    }
}
