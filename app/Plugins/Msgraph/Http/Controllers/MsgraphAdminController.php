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

        return view('msgraph::admin.index', [
            'configured' => MsgraphConfig::isConfigured(),
            'connection' => $connection,
            'calendars' => $calendars,
            'health' => $health,
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

        $connection->forceFill([
            'calendar_id' => $calendarId !== '' ? $calendarId : null,
            'calendar_name' => $calendarName,
        ])->save();
        $connection->audit('msgraph.calendar_selected', ['calendar_name' => $calendarName ?? 'default']);

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
