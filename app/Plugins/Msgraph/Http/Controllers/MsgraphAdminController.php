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

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Http\Controllers\Controller;
use App\Models\{MsgraphConnection, Organization, User};
use App\Plugins\Msgraph\Api\{MsgraphCalendarClient, MsgraphOAuth};
use App\Plugins\Msgraph\MsgraphConfig;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth, Cache};
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Microsoft-365-Admin-Panel + OAuth-Verbindungsflow (MVP-328, Bauturbo A8).
 * Der OAuth-`state` ist kurzlebig, einmalig einlösbar und an Organisation UND
 * Sitzung gebunden (Todoist-Muster); der PKCE-Verifier wandert mit dem state
 * durch den Cache. Tokens erscheinen nie in Logs, Fehlermeldungen oder
 * Audit-Payloads. Scope ist bewusst nur `Calendars.ReadWrite offline_access`.
 */
class MsgraphAdminController extends Controller {
    private const STATE_TTL_SECONDS = 600;

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

    /** Startet den OAuth-Flow: org- und sitzungsgebundener Einmal-state + PKCE. */
    public function startOAuth(MsgraphOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        if (! MsgraphConfig::isConfigured()) {
            return back()->with('error', __('msgraph.flash.not_configured'));
        }

        $state = Str::random(40);
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();
        Cache::put($this->stateKey($state), [
            'organization_id' => (int) $organization->id,
            'user_id' => (int) $admin->id,
            'pkce_verifier' => $verifier,
        ], self::STATE_TTL_SECONDS);

        $url = $oauth->grant()->getAuthorizationUrl($state, $oauth->scopes(), pkceVerifier: $verifier);

        return redirect()->away($url);
    }

    /** OAuth-Callback: state prüfen (einmalig!), Code + PKCE tauschen, Verbindung speichern. */
    public function oauthCallback(Request $request, MsgraphOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        // Einmalig einlösen: pull() entfernt den state sofort (Replay-Schutz).
        $payload = $state !== '' ? Cache::pull($this->stateKey($state)) : null;
        if (! is_array($payload)
            || (int) ($payload['organization_id'] ?? 0) !== (int) $organization->id
            || (int) ($payload['user_id'] ?? 0) !== (int) $admin->id) {
            return redirect()->route('admin.msgraph.index')->with('error', __('msgraph.flash.state_invalid'));
        }

        if ($code === '') {
            return redirect()->route('admin.msgraph.index')->with('error', __('msgraph.flash.oauth_denied'));
        }

        try {
            $token = $oauth->grant()->exchangeAuthorizationCode($code, (string) ($payload['pkce_verifier'] ?? '') ?: null);
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Payload/Token.
            return redirect()->route('admin.msgraph.index')
                ->with('error', __('msgraph.flash.oauth_failed', ['class' => class_basename($e)]));
        }

        /** @var MsgraphConnection $connection */
        $connection = MsgraphConnection::query()->firstOrNew(['organization_id' => $organization->id]);
        $connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken(),
            'token_expires_at' => $token->getExpiresAt(),
            'scopes' => $token->getScope() ?? implode(' ', $oauth->scopes()),
            'status' => MsgraphConnection::STATUS_ACTIVE,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'connected_by' => $admin->id,
            'connected_at' => now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();

        $connection->audit('msgraph.connected', ['by_user_id' => (int) $admin->id]);

        return redirect()->route('admin.msgraph.index')->with('success', __('msgraph.flash.connected'));
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

    /** Trennt die Verbindung (auditiert); publizierte Termine + Referenzen bleiben erhalten. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = MsgraphConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof MsgraphConnection) {
            $connection->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'status' => MsgraphConnection::STATUS_DISCONNECTED,
                'disconnected_by' => $admin->id,
                'disconnected_at' => now(),
            ])->save();
            $connection->audit('msgraph.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return redirect()->route('admin.msgraph.index')->with('success', __('msgraph.flash.disconnected'));
    }

    private function stateKey(string $state): string {
        return 'msgraph-oauth-state:' . $state;
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
