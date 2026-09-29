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

use App\Models\Project\Task;
use App\Plugins\Msgraph\Api\{MsgraphMailOAuth, MsgraphOAuth};
use App\Plugins\Msgraph\Mail\{MsgraphMailTransport, StampOrganizationMailHeader};
use App\Plugins\Msgraph\Observers\MsgraphTodoTaskObserver;
use App\Plugins\Msgraph\Services\{MsgraphOutboxDispatcher, MsgraphSeriesGroupBooker};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\{InboxGroupBookerRegistry, IntegrationOutboxDispatcherResolver};
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
        $this->app->scoped(MsgraphPhoneContactSource::class);
        $this->app->tag([MsgraphPhoneContactSource::class], 'external-phone-contact-sources');

        $this->app->singleton(MsgraphOAuth::class, fn(): MsgraphOAuth => new MsgraphOAuth());
        $this->app->singleton(MsgraphMailOAuth::class, fn(): MsgraphMailOAuth => new MsgraphMailOAuth());
        $this->app->singleton(Api\MsgraphContactsOAuth::class, fn(): Api\MsgraphContactsOAuth => new Api\MsgraphContactsOAuth());

        $this->app->singleton(Api\MsgraphTasksOAuth::class, fn(): Api\MsgraphTasksOAuth => new Api\MsgraphTasksOAuth());

        $this->commands([
            Console\MsgraphCalendarImportCommand::class,
            Console\MsgraphPublishCommand::class,
            Console\MsgraphSubscriptionsCommand::class,
            Console\MsgraphTodoSyncCommand::class,
        ]);
    }

    protected function bootPlugin(): void {
        // Gruppierte Auflösung der Import-Inbox (MVP-1030).
        $this->app->make(InboxGroupBookerRegistry::class)->register(MsgraphPlugin::ID, MsgraphSeriesGroupBooker::class);
        // Graph-Postfächer als Postfach-Transport (MVP-1042).
        $this->app->make(\App\Services\Mail\MailboxTransports::class)->register(new \App\Plugins\Msgraph\Services\MsgraphMailboxTransport);
        // OneNote-Übernahme in die Wissenssammlungen (MVP-1042).
        $this->app->make(\App\Services\Collections\Import\NotebookSources::class)
            ->register(new \App\Plugins\Msgraph\Services\OneNoteImportSource(new \App\Plugins\Msgraph\Services\OneNoteNotebookReader));

        Mail::extend('msgraph', fn(): MsgraphMailTransport => new MsgraphMailTransport());
        Event::listen(MessageSending::class, StampOrganizationMailHeader::class);

        // Live-Export nach Microsoft To Do (Folgeausbau, Todoist-Muster):
        // Observer enqueued nur — die Übertragung läuft über die Outbox.
        Task::observe(MsgraphTodoTaskObserver::class);

        // Feature-103-Delta: Outlook-Abwesenheitsnotiz bei genehmigtem Urlaub
        // (Opt-in je Org, Plugin-Einstellung oof_enabled).
        \App\Models\Absence\Vacation::observe(\App\Plugins\Msgraph\Observers\MsgraphVacationObserver::class);
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new MsgraphOutboxDispatcher());
    }
}
