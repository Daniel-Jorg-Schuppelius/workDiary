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

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Http\Controllers\Controller;
use App\Models\{GoogleCalendarConnection, Organization, User};
use App\Plugins\GoogleCalendar\Api\{GoogleCalendarClient, GoogleCalendarOAuth};
use App\Plugins\GoogleCalendar\GoogleCalendarConfig;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth, Cache};
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Google-Kalender-Admin-Panel + OAuth-Verbindungsflow (MVP-328, Bauturbo A8).
 * Der OAuth-`state` ist kurzlebig, einmalig einlösbar und an Organisation UND
 * Sitzung gebunden (Todoist-Muster); der PKCE-Verifier wandert mit dem state
 * durch den Cache. `access_type=offline` + `prompt=consent` sichern das
 * Refresh-Token. Tokens erscheinen nie in Logs, Fehlermeldungen oder
 * Audit-Payloads.
 */
class GoogleCalendarAdminController extends Controller {
    private const STATE_TTL_SECONDS = 600;

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

    /** Startet den OAuth-Flow: org- und sitzungsgebundener Einmal-state + PKCE. */
    public function startOAuth(GoogleCalendarOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        if (! GoogleCalendarConfig::isConfigured()) {
            return back()->with('error', __('google_calendar.flash.not_configured'));
        }

        $state = Str::random(40);
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();
        Cache::put($this->stateKey($state), [
            'organization_id' => (int) $organization->id,
            'user_id' => (int) $admin->id,
            'pkce_verifier' => $verifier,
        ], self::STATE_TTL_SECONDS);

        // access_type=offline + prompt=consent: nur so liefert Google
        // zuverlässig ein Refresh-Token (auch bei erneuter Verbindung).
        $url = $oauth->grant()->getAuthorizationUrl($state, $oauth->scopes(), [
            'access_type' => 'offline',
            'prompt' => 'consent',
        ], $verifier);

        return redirect()->away($url);
    }

    /** OAuth-Callback: state prüfen (einmalig!), Code + PKCE tauschen, Verbindung speichern. */
    public function oauthCallback(Request $request, GoogleCalendarOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        // Einmalig einlösen: pull() entfernt den state sofort (Replay-Schutz).
        $payload = $state !== '' ? Cache::pull($this->stateKey($state)) : null;
        if (! is_array($payload)
            || (int) ($payload['organization_id'] ?? 0) !== (int) $organization->id
            || (int) ($payload['user_id'] ?? 0) !== (int) $admin->id) {
            return redirect()->route('admin.google-calendar.index')->with('error', __('google_calendar.flash.state_invalid'));
        }

        if ($code === '') {
            return redirect()->route('admin.google-calendar.index')->with('error', __('google_calendar.flash.oauth_denied'));
        }

        try {
            $token = $oauth->grant()->exchangeAuthorizationCode($code, (string) ($payload['pkce_verifier'] ?? '') ?: null);
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Payload/Token.
            return redirect()->route('admin.google-calendar.index')
                ->with('error', __('google_calendar.flash.oauth_failed', ['class' => class_basename($e)]));
        }

        /** @var GoogleCalendarConnection $connection */
        $connection = GoogleCalendarConnection::query()->firstOrNew(['organization_id' => $organization->id]);
        $connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $connection->refresh_token,
            'token_expires_at' => $token->getExpiresAt(),
            'scopes' => $token->getScope() ?? implode(' ', $oauth->scopes()),
            'status' => GoogleCalendarConnection::STATUS_ACTIVE,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'connected_by' => $admin->id,
            'connected_at' => now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();

        $connection->audit('google_calendar.connected', ['by_user_id' => (int) $admin->id]);

        return redirect()->route('admin.google-calendar.index')->with('success', __('google_calendar.flash.connected'));
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

    /** Trennt die Verbindung (auditiert); publizierte Termine + Referenzen bleiben erhalten. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = GoogleCalendarConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof GoogleCalendarConnection) {
            $connection->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'status' => GoogleCalendarConnection::STATUS_DISCONNECTED,
                'disconnected_by' => $admin->id,
                'disconnected_at' => now(),
            ])->save();
            $connection->audit('google_calendar.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return redirect()->route('admin.google-calendar.index')->with('success', __('google_calendar.flash.disconnected'));
    }

    private function stateKey(string $state): string {
        return 'google-calendar-oauth-state:' . $state;
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
