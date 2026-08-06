<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Models\MsgraphConnection;
use App\Plugins\Msgraph\Api\{MsgraphCalendarClient, MsgraphOAuth};
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * Microsoft-365-Admin-Panel + OAuth-Verbindungsflow (MVP-328, Bauturbo A8).
 * Der OAuth-Flow (state einmalig, org-/sitzungsgebunden, PKCE) läuft über die
 * gemeinsame Basis {@see ConnectionOAuthController}. Tokens erscheinen nie in
 * Logs, Fehlermeldungen oder Audit-Payloads. Scope ist bewusst nur
 * `Calendars.ReadWrite offline_access`.
 */
class MsgraphAdminController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = MsgraphConnection::query()->where('organization_id', $organization->id)->first();

        // Verfügbare Ziel-Kalender (nur bei aktiver Verbindung; Fehler → leer,
        // der Health-Status meldet API-Probleme).
        $calendars = [];
        $health = null;
        if ($connection instanceof MsgraphConnection && $connection->isActive()) {
            try {
                $calendars = (new MsgraphCalendarClient($connection))->listCalendars();
                $health = ['ok' => true];
            } catch (Throwable) {
                $health = ['ok' => false];
            }
        }

        // Graph-Mail-Verbindung (Feature 102): eigener Grant, eigene Sektion.
        $mailConnection = \App\Models\MsgraphMailConnection::query()->where('organization_id', $organization->id)->first();

        // Kontakt-Verbindung (Feature 102, Schnitt D): fünfter Grant.
        $contactConnection = \App\Models\MsgraphContactConnection::query()->where('organization_id', $organization->id)->first();

        // To-Do-Sync (Feature 102, Schnitt E): sechster Grant + Listen-Zuordnungen.
        $taskConnection = \App\Models\MsgraphTaskConnection::query()->where('organization_id', $organization->id)->first();
        $todoLists = [];
        if ($taskConnection instanceof \App\Models\MsgraphTaskConnection && $taskConnection->isActive()) {
            try {
                $todoLists = (new \App\Plugins\Msgraph\Api\MsgraphTodoClient($taskConnection))->lists();
            } catch (Throwable) {
                $todoLists = [];
            }
        }
        $taskLinks = \App\Models\MsgraphTaskListLink::query()
            ->where('organization_id', $organization->id)
            ->orderBy('todo_list_name')
            ->get();
        $projects = \App\Models\Project::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('msgraph::admin.index', [
            'configured' => MsgraphConfig::isConfigured(),
            'connection' => $connection,
            'calendars' => $calendars,
            'health' => $health,
            'mailConnection' => $mailConnection,
            'mailerActive' => \App\Plugins\Msgraph\Mail\MsgraphMailTransport::inDefaultMailerChain(),
            'contactConnection' => $contactConnection,
            'taskConnection' => $taskConnection,
            'todoLists' => $todoLists,
            'taskLinks' => $taskLinks,
            'projects' => $projects,
        ]);
    }

    // ── OAuth-Flow: Hooks der gemeinsamen Basis (Vollreview W3a) ──

    protected function oauth(): PluginOAuthGrant {
        return app(MsgraphOAuth::class);
    }

    protected function isConfigured(): bool {
        return MsgraphConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return MsgraphConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'msgraph-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.msgraph.index';
    }

    protected function pluginKey(): string {
        return 'msgraph';
    }

    protected function connectedStatus(): string {
        return MsgraphConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return MsgraphConnection::STATUS_DISCONNECTED;
    }

    /** Wählt den Ziel-Kalender (Name wird serverseitig aus der Kalenderliste aufgelöst). */
    public function selectCalendar(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = MsgraphConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof MsgraphConnection || ! $connection->isActive()) {
            return back()->with('error', __('msgraph.flash.no_connection'));
        }

        $data = $request->validate([
            'calendar_id' => ['nullable', 'string', 'max:512'],
            'teams_meetings' => ['nullable', 'boolean'],
            'two_way' => ['nullable', 'boolean'],
        ]);

        $calendarId = trim((string) ($data['calendar_id'] ?? ''));
        $calendarName = null;
        if ($calendarId !== '') {
            try {
                $match = collect((new MsgraphCalendarClient($connection))->listCalendars())
                    ->firstWhere('id', $calendarId);
            } catch (Throwable) {
                $match = null;
            }
            if (! is_array($match)) {
                return back()->with('error', __('msgraph.flash.calendar_invalid'));
            }
            $calendarName = (string) $match['name'];
        }

        // Kalender-Wechsel = neues Delta-Fenster: Checkpoint verwerfen (C3).
        $calendarChanged = ($calendarId !== '' ? $calendarId : null) !== $connection->calendar_id;

        $connection->forceFill([
            'calendar_id' => $calendarId !== '' ? $calendarId : null,
            'calendar_name' => $calendarName,
            // Teams-Meeting-Link (C1): wirkt nur auf NEU publizierte Termine.
            'teams_meetings' => $request->boolean('teams_meetings'),
            // Zwei-Wege (C3): Rückimport als Inbox-Vorschläge (Opt-in).
            'two_way' => $request->boolean('two_way'),
            'calendar_delta_link' => $calendarChanged ? null : $connection->calendar_delta_link,
        ])->save();
        $connection->audit('msgraph.calendar_selected', ['calendar_name' => $calendarName ?? 'default', 'teams_meetings' => $connection->teams_meetings, 'two_way' => $connection->two_way]);

        return back()->with('success', __('msgraph.flash.calendar_saved'));
    }

    /** Manuelles Publish (auditierter Admin-Vorgang; CalDAV-Muster). */
    public function publish(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = MsgraphConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof MsgraphConnection || ! $connection->isActive()) {
            return back()->with('error', __('msgraph.flash.no_connection'));
        }

        Artisan::call('msgraph:publish', ['--organization' => (string) $organization->id]);
        $connection->audit('msgraph.publish_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('msgraph.flash.publish_done'));
    }
}
