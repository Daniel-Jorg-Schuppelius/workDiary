<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Zammad;

use App\Models\{Task, TimeEntry};
use App\Plugins\Zammad\Contracts\ZammadGatewayFactory;
use App\Plugins\Zammad\Observers\{ZammadTaskObserver, ZammadTimeEntryObserver};
use App\Plugins\Zammad\Services\{ClientGatewayFactory, ZammadOutboxDispatcher};
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 060). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald ZammadPlugin in
 * der Registry steht. Bindet die Gateway-Factory (Tests ersetzen sie durch eine
 * Fake-Variante ohne HTTP), registriert Config-Defaults, Routen, Views und den
 * Polling-Command.
 */
class ZammadServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . ZammadPlugin::ID);

        $this->app->singleton(ZammadGatewayFactory::class, ClientGatewayFactory::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ZammadSyncCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'zammad');

        // Status-Rückkanal (2. Stufe): erledigte, mit einem Ticket verknüpfte
        // Aufgabe → Outbox → Zammad. Import erzeugt Aufgaben nur, daher kein Echo.
        Task::observe(ZammadTaskObserver::class);
        // Zeit-Rückkanal (Rang 23): erfasste Zeit einer verknüpften Aufgabe → Outbox.
        TimeEntry::observe(ZammadTimeEntryObserver::class);
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new ZammadOutboxDispatcher());
    }
}
