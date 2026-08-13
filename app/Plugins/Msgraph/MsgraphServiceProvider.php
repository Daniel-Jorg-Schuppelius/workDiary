<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph;

use App\Models\Task;
use App\Plugins\Msgraph\Api\{MsgraphMailOAuth, MsgraphOAuth};
use App\Plugins\Msgraph\Mail\{MsgraphMailTransport, StampOrganizationMailHeader};
use App\Plugins\Msgraph\Observers\MsgraphTodoTaskObserver;
use App\Plugins\Msgraph\Services\MsgraphOutboxDispatcher;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\{Event, Mail};

/**
 * Plugin-eigener ServiceProvider (MVP-328, Bauturbo A8). Registriert
 * Config-Defaults, Routen, Views und den Publish-Command;
 * {@see MsgraphOAuth}/{@see MsgraphMailOAuth} sind Singletons — Tests ersetzen
 * sie durch Varianten mit Guzzle-MockHandler (Todoist-Muster).
 *
 * Feature 102: registriert außerdem den Symfony-Mailer-Transport `msgraph`
 * (Aktivierung über `MAIL_MAILER=msgraph` bzw. eine failover-Kette) und den
 * Org-Routing-Header-Listener für die Mandantenauflösung im Queue-Worker.
 */
class MsgraphServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return MsgraphPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(MsgraphOAuth::class, fn(): MsgraphOAuth => new MsgraphOAuth());
        $this->app->singleton(MsgraphMailOAuth::class, fn(): MsgraphMailOAuth => new MsgraphMailOAuth());
        $this->app->singleton(Api\MsgraphContactsOAuth::class, fn(): Api\MsgraphContactsOAuth => new Api\MsgraphContactsOAuth());

        $this->app->singleton(Api\MsgraphTasksOAuth::class, fn(): Api\MsgraphTasksOAuth => new Api\MsgraphTasksOAuth());

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MsgraphCalendarImportCommand::class,
                Console\MsgraphPublishCommand::class,
                Console\MsgraphSubscriptionsCommand::class,
                Console\MsgraphTodoSyncCommand::class,
            ]);
        }
    }

    protected function bootPlugin(): void {
        Mail::extend('msgraph', fn(): MsgraphMailTransport => new MsgraphMailTransport());
        Event::listen(MessageSending::class, StampOrganizationMailHeader::class);

        // Live-Export nach Microsoft To Do (Folgeausbau, Todoist-Muster):
        // Observer enqueued nur — die Übertragung läuft über die Outbox.
        Task::observe(MsgraphTodoTaskObserver::class);

        // Feature-103-Delta: Outlook-Abwesenheitsnotiz bei genehmigtem Urlaub
        // (Opt-in je Org, settings.msgraph.oof_enabled).
        \App\Models\Vacation::observe(\App\Plugins\Msgraph\Observers\MsgraphVacationObserver::class);
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new MsgraphOutboxDispatcher());
    }
}
