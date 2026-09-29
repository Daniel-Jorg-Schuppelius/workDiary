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

use App\Models\Project\Task;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Plugins\Todoist\Api\TodoistOAuth;
use App\Plugins\Todoist\Observers\TodoistTaskObserver;
use App\Plugins\Todoist\Services\TodoistOutboxDispatcher;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;

/**
 * Plugin-eigener ServiceProvider (Feature 055). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald TodoistPlugin
 * in der Registry steht. Registriert Config-Defaults, Routen, Views, den
 * Export-Observer (MVP-114) und den Outbox-Dispatcher an der Registry;
 * {@see TodoistOAuth} ist Singleton — Tests ersetzen ihn durch eine Variante
 * mit Guzzle-MockHandler.
 */
class TodoistServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return TodoistPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(TodoistOAuth::class, fn (): TodoistOAuth => new TodoistOAuth());

        $this->commands([
            Console\TodoistSyncCommand::class,
        ]);
    }

    protected function bootPlugin(): void {
        // Sessionloser Webhook (Feature 055, MVP-115): Bursts erlauben, Flooding deckeln;
        // Verluste heilt der stündliche Polling-Abgleich (todoist:sync).
        \Illuminate\Support\Facades\RateLimiter::for('todoist-webhook', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by('twh:' . $request->ip()));
        // Ablaufprüfung der Verbindungen (MVP-1044).
        $this->app->make(\App\Services\Operations\Expiry\ExpiryScanner::class)->extend(new \App\Plugins\Todoist\Services\TodoistExpiryProbe);
        Task::observe(TodoistTaskObserver::class);
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new TodoistOutboxDispatcher());
    }
}
