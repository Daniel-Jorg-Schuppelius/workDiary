<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Plugins\Support\PluginServiceProviderBase;
use App\Plugins\Toggl\Console\{TogglBackfillReferencesCommand, TogglImportCommand, TogglPushCommand, TogglRepairEntryBillableCommand, TogglRepairEntryUsersCommand};
use App\Plugins\Toggl\Services\TogglOutboxDispatcher;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;

/**
 * Plugin-eigener ServiceProvider. Wird vom Core-{@see \App\Providers\PluginServiceProvider}
 * geladen, sobald TogglPlugin in der Registry steht. Registriert den Service,
 * lädt Routes + Views und stellt den Import-Command bereit.
 */
class TogglServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return TogglPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(TogglImportService::class, fn(): TogglImportService => new TogglImportService);
    }

    protected function bootPlugin(): void {
        // Rückrichtung (MVP-437): lokale Korrekturen an importierten Zeiten
        // gehen über die Integrations-Outbox zurück nach Toggl.
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new TogglOutboxDispatcher);

        if ($this->app->runningInConsole()) {
            $this->commands([
                TogglImportCommand::class,
                TogglPushCommand::class,
                TogglBackfillReferencesCommand::class,
                TogglRepairEntryUsersCommand::class,
                TogglRepairEntryBillableCommand::class,
            ]);
        }
    }
}
