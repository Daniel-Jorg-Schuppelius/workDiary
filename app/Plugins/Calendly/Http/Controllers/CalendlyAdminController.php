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

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\User\Permission;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Plugins\Calendly\Api\{CalendlyClient, CalendlyOAuth};
use App\Plugins\Calendly\{CalendlyConfig, CalendlyPlugin};
use App\Plugins\Calendly\Enums\{CalendlyConnectionStatus, CalendlyWebhookSubscriptionStatus};
use App\Plugins\Calendly\Models\{CalendlyConnection, CalendlyWebhookSubscription};
use App\Plugins\Calendly\Services\{CalendlyAppointmentMatcher, CalendlyBackfillService, CalendlyConfirmService, CalendlyOutboundService, CalendlySubscriptionManager};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use App\Support\{OrganizationContext, SqidEncoder};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
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
            ->where('status', CalendlyWebhookSubscriptionStatus::Active)
            ->first();
        $requests = AppointmentRequest::query()
            ->where('organization_id', $organization->id)
            ->where('source', AppointmentRequest::SOURCE_CALENDLY)
            ->where('status', AppointmentRequestStatus::Requested)
            ->orderBy('start_at')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('calendly::admin.index', [
            'configured' => CalendlyConfig::isConfigured(),
            'connection' => $connection,
            'subscription' => $subscription,
            'requests' => $requests,
            'hostMappings' => ExternalReference::query()
                ->forPlugin($organization, CalendlyPlugin::ID, CalendlyAppointmentMatcher::HOST_REFERENCE)
                ->with('referenceable')
                ->orderBy('external_id')
                ->get(),
            'users' => User::query()
                ->where('organization_id', $organization->id)
                ->whereNull('customer_id')
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /** Calendly-Gastgeber mit abweichender Adresse einem Benutzer zuordnen (MVP-1610). */
    public function mapHost(Request $request, SqidEncoder $sqids): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'user' => ['required', 'string'],
        ]);
        $user = User::query()
            ->where('organization_id', $organization->id)
            ->whereNull('customer_id')
            ->find($sqids->decode(User::class, (string) $data['user']));
        abort_if($user === null, 422);

        $email = mb_strtolower(trim((string) $data['email']));
        ExternalReference::link($organization, CalendlyPlugin::ID, CalendlyAppointmentMatcher::HOST_REFERENCE, $user, $email);
        $user->audit('calendly.user_mapped', ['calendly_email' => $email, 'by_user_id' => (int) $admin->id]);

        return back()->with('success', __('Calendly-Nutzer zugeordnet.'));
    }

    public function unmapHost(ExternalReference $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $reference->organization_id === (int) $organization->id
            && $reference->plugin_id === CalendlyPlugin::ID
            && $reference->external_type === CalendlyAppointmentMatcher::HOST_REFERENCE, 404);

        $user = $reference->referenceable;
        $reference->delete();
        if ($user instanceof User) {
            $user->audit('calendly.user_unmapped', ['calendly_email' => $reference->external_id, 'by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('Calendly-Zuordnung entfernt.'));
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

    protected function pluginId(): string {
        return CalendlyPlugin::ID;
    }

    protected function connectedStatus(): string {
        return CalendlyConnectionStatus::Active->value;
    }

    protected function disconnectedStatus(): string {
        return CalendlyConnectionStatus::Disconnected->value;
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
            'not_configured' => __('Calendly ist nicht konfiguriert: weder eine eigene App in den Plugin-Einstellungen noch die Instanz-App.'),
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

    /** Bestätigen und Ablehnen darf, wer die Disposition führt — dasselbe Recht wie im Termin-Eingang (MVP-1610). */
    public function confirm(AppointmentRequest $appointmentRequest, CalendlyConfirmService $service): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);

        $entry = $service->confirm($appointmentRequest, $this->authUser());
        if ($entry === null) {
            return back()->with('error', __('Terminwunsch konnte nicht bestätigt werden.'));
        }

        return back()->with('success', __('Termin bestätigt und disponiert.'));
    }

    public function decline(Request $request, AppointmentRequest $appointmentRequest, CalendlyConfirmService $service): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $service->decline($appointmentRequest, $this->authUser(), $data['reason'] ?? null);

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
