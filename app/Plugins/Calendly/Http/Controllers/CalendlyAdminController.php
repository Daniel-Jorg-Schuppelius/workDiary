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

use App\Models\{AppointmentRequest, CalendlyConnection, CalendlyWebhookSubscription, User};
use App\Plugins\Calendly\Api\{CalendlyClient, CalendlyOAuth};
use App\Plugins\Calendly\CalendlyConfig;
use App\Plugins\Calendly\Services\{CalendlyBackfillService, CalendlyConfirmService, CalendlyOutboundService, CalendlySubscriptionManager};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Calendly-Admin-Panel + OAuth-Verbindungsflow (Feature 095). Der OAuth-Flow
 * (state einmalig, org-/sitzungsgebunden, PKCE) läuft über die gemeinsame
 * Basis {@see ConnectionOAuthController}; Tokens erscheinen nie in Logs,
 * Fehlermeldungen oder Audit-Payloads. Die zweiphasige Bestätigung der
 * Terminwünsche läuft über {@see CalendlyConfirmService}.
 */
class CalendlyAdminController extends ConnectionOAuthController {
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

    // ── OAuth-Flow: Hooks der gemeinsamen Basis (Vollreview W3a) ──

    protected function oauth(): PluginOAuthGrant {
        return app(CalendlyOAuth::class);
    }

    protected function isConfigured(): bool {
        return CalendlyConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return CalendlyConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'calendly-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.calendly.index';
    }

    protected function pluginKey(): string {
        return 'calendly';
    }

    protected function connectedStatus(): string {
        return CalendlyConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return CalendlyConnection::STATUS_DISCONNECTED;
    }

    protected function keepsRefreshTokenOnReconnect(): bool {
        return true;
    }

    /**
     * Calendly flasht wörtliche Texte statt Lang-Keys (Bestand, Feature 095).
     *
     * @param  array<string, string>  $replace
     */
    protected function flashMessage(string $name, array $replace = []): string {
        $message = match ($name) {
            'not_configured' => __('Calendly Client-ID/Secret sind nicht konfiguriert.'),
            'state_invalid' => __('Ungültiger oder abgelaufener OAuth-Status.'),
            'oauth_denied' => __('OAuth-Autorisierung abgebrochen.'),
            'oauth_failed' => __('OAuth fehlgeschlagen (:class).', $replace),
            'connected' => __('Calendly verbunden.'),
            'disconnected' => __('Calendly-Verbindung getrennt.'),
            default => $name,
        };

        return is_string($message) ? $message : $name;
    }

    /** /users/me: verbundenen Nutzer + Organisation-URI (Scope-Ziel) ermitteln. */
    protected function afterConnected(Model $connection, User $admin): void {
        /** @var CalendlyConnection $connection */
        $me = (new CalendlyClient($connection))->currentUser();
        if (is_array($me)) {
            $connection->forceFill([
                'calendly_user_uri' => is_string($me['uri'] ?? null) ? $me['uri'] : null,
                'calendly_organization_uri' => is_string($me['current_organization'] ?? null) ? $me['current_organization'] : null,
            ])->save();
        }
    }

    /** Webhook-Abmeldung vor dem Trennen (Token ist noch gültig). */
    protected function beforeDisconnect(Model $connection): void {
        /** @var CalendlyConnection $connection */
        app(CalendlySubscriptionManager::class)->remove($connection);
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

    /**
     * Outbound (P5): Einmal-Buchungslink je Lead/Leistung über
     * `POST /one_off_event_types` erzeugen; die `scheduling_url` wird zum
     * Teilen geflasht (nicht auditiert — der Link ist ein Zugriffsartefakt).
     */
    public function createBookingLink(Request $request, CalendlyOutboundService $outbound): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'integer', 'min:5', 'max:480'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $connection = CalendlyConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof CalendlyConnection || ! $connection->isActive()) {
            return back()->with('error', __('Keine aktive Calendly-Verbindung.'));
        }

        $url = $outbound->createBookingLink(
            $connection,
            $data['name'],
            (int) $data['duration'],
            endDate: CarbonImmutable::now()->addDays((int) ($data['days'] ?? 30)),
        );
        if ($url === null) {
            return back()->with('error', __('Buchungslink konnte nicht erzeugt werden.'));
        }

        $connection->audit('calendly.booking_link_created', ['by_user_id' => (int) $admin->id, 'name' => $data['name']]);

        return back()
            ->with('success', __('Einmal-Buchungslink erzeugt.'))
            ->with('calendly_booking_url', $url);
    }
}
