<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{AppointmentRequest, CalendlyConnection, CalendlyWebhookSubscription};
use App\Plugins\Calendly\Api\{CalendlyClient, CalendlyOAuth};
use App\Plugins\Calendly\CalendlyConfig;
use App\Plugins\Calendly\Services\{CalendlyBackfillService, CalendlyConfirmService, CalendlySubscriptionManager};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\OAuthStateHandshake;
use App\Support\OrganizationContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use Throwable;

/**
 * Calendly-Admin-Panel + OAuth-Verbindungsflow (Feature 095). Der OAuth-`state`
 * ist kurzlebig, einmalig einlösbar und an Organisation UND Sitzung gebunden;
 * der PKCE-Verifier wandert mit dem state durch den Cache. Tokens erscheinen nie
 * in Logs, Fehlermeldungen oder Audit-Payloads. Die zweiphasige Bestätigung der
 * Terminwünsche läuft über {@see CalendlyConfirmService}.
 */
class CalendlyAdminController extends Controller {
    use ResolvesPluginOrgContext;

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CalendlyConnection::query()->where('organization_id', $organization->id)->first();
        $subscription = CalendlyWebhookSubscription::query()
            ->where('organization_id', $organization->id)
            ->where('status', CalendlyWebhookSubscription::STATUS_ACTIVE)
            ->first();
        $requests = AppointmentRequest::query()
            ->where('organization_id', $organization->id)
            ->where('source', AppointmentRequest::SOURCE_CALENDLY)
            ->where('status', AppointmentRequest::STATUS_REQUESTED)
            ->orderBy('start_at')
            ->limit(100)
            ->get();

        return view('calendly::admin.index', [
            'configured' => CalendlyConfig::isConfigured(),
            'connection' => $connection,
            'subscription' => $subscription,
            'requests' => $requests,
        ]);
    }

    public function startOAuth(CalendlyOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        if (! CalendlyConfig::isConfigured()) {
            return back()->with('error', __('Calendly Client-ID/Secret sind nicht konfiguriert.'));
        }

        ['state' => $state, 'verifier' => $verifier] = $this->handshake()->start((int) $organization->id, (int) $admin->id);
        $url = $oauth->grant()->getAuthorizationUrl($state, $oauth->scopes(), [], $verifier);

        return redirect()->away($url);
    }

    public function oauthCallback(Request $request, CalendlyOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $code = (string) $request->query('code', '');
        $payload = $this->handshake()->redeem((string) $request->query('state', ''), (int) $organization->id, (int) $admin->id);
        if ($payload === null) {
            return redirect()->route('admin.calendly.index')->with('error', __('Ungültiger oder abgelaufener OAuth-Status.'));
        }
        if ($code === '') {
            return redirect()->route('admin.calendly.index')->with('error', __('OAuth-Autorisierung abgebrochen.'));
        }

        try {
            $token = $oauth->grant()->exchangeAuthorizationCode($code, OAuthStateHandshake::verifierFrom($payload));
        } catch (Throwable $e) {
            return redirect()->route('admin.calendly.index')
                ->with('error', __('OAuth fehlgeschlagen (:class).', ['class' => class_basename($e)]));
        }

        /** @var CalendlyConnection $connection */
        $connection = CalendlyConnection::query()->firstOrNew(['organization_id' => $organization->id]);
        $connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $connection->refresh_token,
            'token_expires_at' => $token->getExpiresAt(),
            'scopes' => $token->getScope() ?? implode(' ', $oauth->scopes()),
            'status' => CalendlyConnection::STATUS_ACTIVE,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'connected_by' => $admin->id,
            'connected_at' => now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();

        // /users/me: verbundenen Nutzer + Organisation-URI (Scope-Ziel) ermitteln.
        $me = (new CalendlyClient($connection))->currentUser();
        if (is_array($me)) {
            $connection->forceFill([
                'calendly_user_uri' => is_string($me['uri'] ?? null) ? $me['uri'] : null,
                'calendly_organization_uri' => is_string($me['current_organization'] ?? null) ? $me['current_organization'] : null,
            ])->save();
        }

        $connection->audit('calendly.connected', ['by_user_id' => (int) $admin->id]);

        return redirect()->route('admin.calendly.index')->with('success', __('Calendly verbunden.'));
    }

    public function subscribe(CalendlySubscriptionManager $subscriptions): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CalendlyConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof CalendlyConnection || ! $connection->isActive()) {
            return back()->with('error', __('Keine aktive Calendly-Verbindung.'));
        }

        $subscription = $subscriptions->ensure($connection);
        if (! $subscription instanceof CalendlyWebhookSubscription) {
            return back()->with('error', __('Webhook-Anmeldung fehlgeschlagen.'));
        }
        $connection->audit('calendly.subscribed', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('Webhook-Anmeldung aktiv.'));
    }

    public function backfill(CalendlyBackfillService $service): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $result = OrganizationContext::run($organization, fn(): array => $service->sync($organization));

        return back()->with('success', __('Backfill abgeschlossen (:created neu, :updated aktualisiert).', [
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]));
    }

    public function confirm(AppointmentRequest $appointmentRequest, CalendlyConfirmService $service): RedirectResponse {
        $admin = $this->admin();

        $entry = $service->confirm($appointmentRequest, $admin);
        if ($entry === null) {
            return back()->with('error', __('Terminwunsch konnte nicht bestätigt werden.'));
        }

        return back()->with('success', __('Termin bestätigt und disponiert.'));
    }

    public function decline(Request $request, AppointmentRequest $appointmentRequest, CalendlyConfirmService $service): RedirectResponse {
        $admin = $this->admin();
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $service->decline($appointmentRequest, $admin, $data['reason'] ?? null);

        return back()->with('success', __('Terminwunsch abgelehnt.'));
    }

    public function disconnect(CalendlySubscriptionManager $subscriptions): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CalendlyConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof CalendlyConnection) {
            $subscriptions->remove($connection);
            $connection->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'status' => CalendlyConnection::STATUS_DISCONNECTED,
                'disconnected_by' => $admin->id,
                'disconnected_at' => now(),
            ])->save();
            $connection->audit('calendly.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return redirect()->route('admin.calendly.index')->with('success', __('Calendly-Verbindung getrennt.'));
    }

    private function handshake(): OAuthStateHandshake {
        return new OAuthStateHandshake('calendly-oauth-state');
    }
}
