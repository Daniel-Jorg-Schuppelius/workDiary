<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax;

use App\Plugins\OrgaMax\Console\OrgaMaxSyncCommand;
use App\Plugins\OrgaMax\Services\OrgaMaxOutboxDispatcher;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Bootet das orgaMAX-Plugin (Feature 077): Routen, Views, Console-Command
 * und die Registrierung des Outbox-Dispatchers für Schreibbefehle
 * (invoice.convert / invoice.send / payment.push).
 */
class OrgaMaxServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . OrgaMaxPlugin::ID);

        if ($this->app->runningInConsole()) {
            $this->commands([OrgaMaxSyncCommand::class]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', OrgaMaxPlugin::ID);

        $this->app->make(IntegrationOutboxDispatcherResolver::class)
            ->register($this->app->make(OrgaMaxOutboxDispatcher::class));
    }
}
