<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\CalDav;

use App\Plugins\CalDav\Contracts\CalDavGatewayFactory;
use App\Plugins\CalDav\Services\{CalDavSeriesGroupBooker, GuzzleCalDavGatewayFactory};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\InboxGroupBookerRegistry;

/**
 * Plugin-eigener ServiceProvider (Feature 058). Bindet die Gateway-Factory
 * (Tests ersetzen sie durch eine Fake-Variante ohne HTTP), registriert
 * Config-Defaults, Routen, Views und den Publish-Command.
 */
class CalDavServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return CalDavPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(CalDavGatewayFactory::class, GuzzleCalDavGatewayFactory::class);

        $this->commands([
            Console\CalDavPublishCommand::class,
            Console\CalDavImportCommand::class,
        ]);
    }

    protected function bootPlugin(): void {
        // Erweiterungspunkt des Moduls (MVP-1045): Termine als Zeitimport-Feed.
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Import\Contracts\CalendarImportFeed::class, \App\Plugins\CalDav\Services\CalDavImportFeed::class);
        // Verbindungszustand für Diagnose und Ablaufprüfung (MVP-1044).
        $this->app->make(\App\Services\Diagnostics\ConnectionHealthModels::class)->register('caldav', \App\Plugins\CalDav\Models\CalDavConnection::class, operationsTask: true);
        // Gruppierte Auflösung der Import-Inbox (MVP-1030).
        $this->app->make(InboxGroupBookerRegistry::class)->register(CalDavPlugin::ID, CalDavSeriesGroupBooker::class);
    }
}
