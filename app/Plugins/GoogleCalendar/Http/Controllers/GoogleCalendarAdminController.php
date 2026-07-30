<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar\Http\Controllers;

use App\Models\GoogleCalendarConnection;
use App\Plugins\GoogleCalendar\Api\{GoogleCalendarClient, GoogleCalendarOAuth};
use App\Plugins\GoogleCalendar\GoogleCalendarConfig;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * Google-Kalender-Admin-Panel + OAuth-Verbindungsflow (MVP-328, Bauturbo A8).
 * Der OAuth-Flow (state einmalig, org-/sitzungsgebunden, PKCE) läuft über die
 * gemeinsame Basis {@see ConnectionOAuthController}; `access_type=offline` +
 * `prompt=consent` sichern das Refresh-Token. Tokens erscheinen nie in Logs,
 * Fehlermeldungen oder Audit-Payloads.
 */
class GoogleCalendarAdminController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = GoogleCalendarConnection::query()->where('organization_id', $organization->id)->first();

        // Verfügbare Ziel-Kalender (nur bei aktiver Verbindung; Fehler → leer,
        // der Health-Status meldet API-Probleme).
        $calendars = [];
        $health = null;
        if ($connection instanceof GoogleCalendarConnection && $connection->isActive()) {
            try {
                $calendars = (new GoogleCalendarClient($connection))->listCalendars();
                $health = ['ok' => true];
            } catch (Throwable) {
                $health = ['ok' => false];
            }
        }

        return view('google_calendar::admin.index', [
            'configured' => GoogleCalendarConfig::isConfigured(),
            'connection' => $connection,
            'calendars' => $calendars,
            'health' => $health,
        ]);
    }

    // ── OAuth-Flow: Hooks der gemeinsamen Basis (Vollreview W3a) ──

    protected function oauth(): PluginOAuthGrant {
        return app(GoogleCalendarOAuth::class);
    }

    protected function isConfigured(): bool {
        return GoogleCalendarConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return GoogleCalendarConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'google-calendar-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.google-calendar.index';
    }

    protected function pluginKey(): string {
        return 'google_calendar';
    }

    protected function connectedStatus(): string {
        return GoogleCalendarConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return GoogleCalendarConnection::STATUS_DISCONNECTED;
    }

    protected function keepsRefreshTokenOnReconnect(): bool {
        return true;
    }

    /**
     * access_type=offline + prompt=consent: nur so liefert Google
     * zuverlässig ein Refresh-Token (auch bei erneuter Verbindung).
     *
     * @return array<string, string>
     */
    protected function extraAuthorizeParams(): array {
        return [
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
    }

    /** Wählt den Ziel-Kalender (Name wird serverseitig aus der Kalenderliste aufgelöst). */
    public function selectCalendar(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = GoogleCalendarConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof GoogleCalendarConnection || ! $connection->isActive()) {
            return back()->with('error', __('google_calendar.flash.no_connection'));
        }

        $data = $request->validate([
            'calendar_id' => ['nullable', 'string', 'max:255'],
        ]);

        $calendarId = trim((string) ($data['calendar_id'] ?? ''));
        $calendarName = null;
        if ($calendarId !== '') {
            try {
                $match = collect((new GoogleCalendarClient($connection))->listCalendars())
                    ->firstWhere('id', $calendarId);
            } catch (Throwable) {
                $match = null;
            }
            if (! is_array($match)) {
                return back()->with('error', __('google_calendar.flash.calendar_invalid'));
            }
            $calendarName = (string) $match['name'];
        }

        $connection->forceFill([
            'calendar_id' => $calendarId !== '' ? $calendarId : null,
            'calendar_name' => $calendarName,
        ])->save();
        $connection->audit('google_calendar.calendar_selected', ['calendar_name' => $calendarName ?? 'primary']);

        return back()->with('success', __('google_calendar.flash.calendar_saved'));
    }

    /** Manuelles Publish (auditierter Admin-Vorgang; CalDAV-Muster). */
    public function publish(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = GoogleCalendarConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof GoogleCalendarConnection || ! $connection->isActive()) {
            return back()->with('error', __('google_calendar.flash.no_connection'));
        }

        Artisan::call('google-calendar:publish', ['--organization' => (string) $organization->id]);
        $connection->audit('google_calendar.publish_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('google_calendar.flash.publish_done'));
    }
}
