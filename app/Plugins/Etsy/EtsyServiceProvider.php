<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy;

use App\Plugins\Etsy\Console\EtsySyncCommand;
use App\Plugins\Etsy\Services\EtsyShipmentDispatcher;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;

/**
 * Bootet das Etsy-Plugin (Feature 101, MVP-494–498): Config-Defaults,
 * Admin-/Webhook-Routen, Views, Sync-Command sowie den Outbox-Dispatcher
 * für den Versand-Rückkanal (MVP-497).
 */
class EtsyServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return EtsyPlugin::ID;
    }

    protected function registerPlugin(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([EtsySyncCommand::class]);
        }
    }

    protected function bootPlugin(): void {
        app(IntegrationOutboxDispatcherResolver::class)->register(app(EtsyShipmentDispatcher::class));
    }
}
