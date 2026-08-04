<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Http\Controllers;

use App\Models\{EtsyConnection, EtsyReceipt, IntegrationInboxItem, User};
use App\Plugins\Etsy\Api\{EtsyClientFactory, EtsyOAuthGrant};
use App\Plugins\Etsy\Services\{EtsyLedgerImportService, EtsyReceiptImportService};
use App\Plugins\Etsy\{EtsyConfig, EtsyPlugin};
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use App\Services\Integration\IntegrationOutboxService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Etsy-Admin-Panel + OAuth-Verbindungsflow (Feature 101). Der OAuth-Flow
 * (state einmalig, org-/sitzungsgebunden, PKCE-Pflicht) läuft über die
 * gemeinsame Basis {@see ConnectionOAuthController}; Tokens erscheinen nie
 * in Logs, Fehlermeldungen oder Audit-Payloads. Das Panel zeigt den
 * Pflicht-Disclaimer (Etsy-ToS), die Org-eigene Webhook-URL fürs
 * Etsy-Portal und den Bestellspiegel mit Versand-Aktion (MVP-497).
 */
class EtsyAdminController extends ConnectionOAuthController {
    public function index(Request $request): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = EtsyConnection::query()->where('organization_id', $organization->id)->first();

        $status = trim((string) $request->query('status', ''));
        $receipts = EtsyReceipt::query()
            ->with('customer')
            ->where('organization_id', $organization->id)
            ->when($status !== '', fn($q) => $q->where('status', $status))
            ->orderByDesc('ordered_at')
            ->paginate(25)
            ->withQueryString();

        $statuses = EtsyReceipt::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $openInbox = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', EtsyPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count();

        // Ledger-Summen je Art (90 Tage) — amount ist Etsy-roh in kleinster
        // Währungseinheit (MVP-498), die Anzeige teilt durch 100.
        $ledgerSums = \App\Models\EtsyLedgerEntry::query()
            ->where('organization_id', $organization->id)
            ->where('posted_at', '>=', now()->subDays(90))
            ->selectRaw('ledger_type, currency, SUM(amount) as amount_sum, COUNT(*) as entries')
            ->groupBy('ledger_type', 'currency')
            ->orderBy('ledger_type')
            ->get();

        return view(EtsyPlugin::ID . '::admin.index', [
            'configured' => EtsyConfig::isConfigured((int) $organization->id),
            'connection' => $connection,
            'receipts' => $receipts,
            'statuses' => $statuses,
            'status' => $status,
            'openInbox' => $openInbox,
            'ledgerSums' => $ledgerSums,
            'webhookUrl' => $connection?->webhook_token !== null && $connection->webhook_token !== ''
                ? route('api.webhooks.etsy', ['token' => $connection->webhook_token])
                : null,
            'callbackUrl' => route('admin.etsy.oauth.callback'),
        ]);
    }

    public function syncNow(EtsyReceiptImportService $receipts, EtsyLedgerImportService $ledger): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        try {
            $counters = $receipts->import($organization) + ['ledger' => $ledger->import($organization)];

            return back()->with('success', (string) __('etsy.flash.synced', [
                'imported' => $counters['imported'],
                'staged' => $counters['staged'],
            ]));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', (string) __('etsy.flash.sync_failed'));
        }
    }

    /**
     * Versandmeldung (MVP-497): reiht `receipt_shipped` idempotent in die
     * Integrations-Outbox ein — der Dispatcher meldet Tracking + Carrier an
     * Etsy und stempelt den Spiegel. Manuelle Aktion (Policy §18.1).
     */
    public function ship(Request $request, EtsyReceipt $etsyReceipt, IntegrationOutboxService $outbox): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $etsyReceipt->organization_id === (int) $organization->id, 404);

        $data = $request->validate([
            'tracking_code' => ['nullable', 'string', 'max:100'],
            'carrier_name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($etsyReceipt->shipped_pushed_at !== null || $etsyReceipt->was_shipped) {
            return back()->with('success', (string) __('etsy.flash.already_shipped'));
        }

        $outbox->enqueue(
            (int) $organization->id,
            EtsyPlugin::ID,
            'receipt_shipped',
            array_filter([
                'tracking_code' => trim((string) ($data['tracking_code'] ?? '')) ?: null,
                'carrier_name' => trim((string) ($data['carrier_name'] ?? '')) ?: null,
            ], static fn(?string $value): bool => $value !== null),
            'etsy:ship:' . $etsyReceipt->receipt_id,
            $etsyReceipt,
        );

        return back()->with('success', (string) __('etsy.flash.ship_queued'));
    }

    // ── OAuth-Flow: Hooks der gemeinsamen Basis ──

    protected function oauth(): PluginOAuthGrant {
        return app(EtsyOAuthGrant::class);
    }

    protected function isConfigured(): bool {
        return EtsyConfig::isConfigured((int) $this->organization($this->admin())->id);
    }

    protected function connectionModel(): string {
        return EtsyConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'etsy-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.etsy.index';
    }

    protected function pluginKey(): string {
        return 'etsy';
    }

    protected function connectedStatus(): string {
        return EtsyConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return EtsyConnection::STATUS_DISCONNECTED;
    }

    protected function keepsRefreshTokenOnReconnect(): bool {
        return true;
    }

    /**
     * Etsy flasht wörtliche Texte statt Lang-Keys (Muster Calendly).
     *
     * @param  array<string, string>  $replace
     */
    protected function flashMessage(string $name, array $replace = []): string {
        $message = match ($name) {
            'not_configured' => __('Etsy Keystring/Shared Secret sind nicht konfiguriert (Plugin-Karte).'),
            'state_invalid' => __('Ungültiger oder abgelaufener OAuth-Status.'),
            'oauth_denied' => __('OAuth-Autorisierung abgebrochen.'),
            'oauth_failed' => __('OAuth fehlgeschlagen (:class).', $replace),
            'connected' => __('Etsy verbunden.'),
            'disconnected' => __('Etsy-Verbindung getrennt.'),
            default => $name,
        };

        return is_string($message) ? $message : $name;
    }

    /**
     * Shop-Ermittlung nach dem Token-Tausch: die Etsy-User-ID steckt im
     * Access-Token-Präfix (`{user_id}.{token}`), der Shop kommt über
     * `GET /users/{user_id}/shops`. Ein Shop, der bereits an einer anderen
     * Organisation hängt, wird NICHT übernommen (Unique-Grenze) — die
     * Verbindung bleibt ohne Shop und das Panel zeigt den Konflikt.
     */
    protected function afterConnected(Model $connection, User $admin): void {
        /** @var EtsyConnection $connection */
        if (trim((string) $connection->webhook_token) === '') {
            $connection->forceFill(['webhook_token' => Str::random(48)]);
        }

        $userId = $this->userIdFromToken((string) $connection->access_token);
        $connection->forceFill(['etsy_user_id' => $userId])->save();
        if ($userId === null) {
            return;
        }

        try {
            $shop = app(EtsyClientFactory::class)->for($connection)->userShop($userId);
        } catch (Throwable) {
            return; // Panel zeigt „Shop-Ermittlung offen"; der Healthcheck meldet Details.
        }
        if (! is_array($shop) || ! is_numeric($shop['shop_id'] ?? null)) {
            return;
        }

        $shopId = (int) $shop['shop_id'];
        $boundElsewhere = EtsyConnection::query()
            ->withoutGlobalScopes()
            ->where('shop_id', $shopId)
            ->where('organization_id', '!=', $connection->organization_id)
            ->exists();
        if ($boundElsewhere) {
            $connection->recordConnectionFailure('shop_already_bound');

            return;
        }

        $connection->forceFill([
            'shop_id' => $shopId,
            'shop_name' => is_string($shop['shop_name'] ?? null) ? $shop['shop_name'] : null,
        ])->save();
    }

    /** `{user_id}.{token}` → numerisches Präfix; null bei fremdem Format. */
    private function userIdFromToken(string $accessToken): ?int {
        $prefix = strstr($accessToken, '.', true);

        return ($prefix !== false && $prefix !== '' && ctype_digit($prefix)) ? (int) $prefix : null;
    }
}
