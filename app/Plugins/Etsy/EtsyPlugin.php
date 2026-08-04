<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy;

use App\Models\{EtsyConnection, Organization};
use App\Plugins\Etsy\Api\{EtsyApiException, EtsyClientFactory};
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Support\PluginOrgContext;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Etsy-Marktplatz-Plugin (Feature 101, MVP-494–498, Phase 66): Direktanbindung
 * der Etsy Open API v3 nach dem Billbee-Muster — Bestell-Spiegel
 * `etsy_receipts` Inbox-First, Webhooks als Beschleuniger, Versand-Rückkanal
 * über die Integrations-Outbox, Ledger-Import für die Buchhaltung.
 *
 * - Jede Organisation registriert ihre EIGENE Etsy-Seller-App (Keystring +
 *   Shared Secret in den Plugin-Settings) — kein Commercial Access nötig.
 * - OAuth2 Authorization Code MIT PKCE (Public Client); Refresh-Token
 *   rotiert und läuft nach 90 Tagen Inaktivität ab (Reconnect-Warnung im
 *   Healthcheck).
 * - Keine Capability: Bestellungen laufen über den Spiegel, nicht über eine
 *   Provider-Registry (Billbee-Muster).
 */
class EtsyPlugin extends AbstractPlugin {
    public const ID = 'etsy';

    public const SERVICE_PROVIDER = EtsyServiceProvider::class;

    /** Refresh-Token-Alter (Tage), ab dem der Healthcheck zur Neuverbindung rät. */
    private const REFRESH_WARN_AFTER_DAYS = 80;

    public function name(): string {
        return 'Etsy';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Bindet den Etsy-Shop der Organisation direkt an (Open API v3): Bestellungen landen Inbox-First im Spiegel, Versandmeldungen mit Tracking fließen zurück, Gebühren und Auszahlungen stehen der Auswertung zur Verfügung. Hinweis: „Etsy" ist eine Marke der Etsy, Inc. Diese Anwendung nutzt die Etsy-API, ist aber von Etsy, Inc. weder unterstützt noch zertifiziert.');
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> Orders laufen über den Spiegel (Billbee-Muster). */
    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.etsy.index',
            'label' => __('Etsy'),
            'icon' => 'storefront',
        ];
    }

    /** @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}> */
    public function settingsSchema(): array {
        return [
            ['key' => 'keystring', 'label' => __('Keystring (Client-ID)'), 'type' => 'password', 'required' => true, 'help' => __('Aus der Org-eigenen Etsy-Seller-App (etsy.com/developers → Your Apps).')],
            ['key' => 'shared_secret', 'label' => __('Shared Secret'), 'type' => 'password', 'required' => true, 'help' => __('Zweiter Teil des x-api-key-Headers; steht neben dem Keystring auf der Your-Apps-Seite.')],
            ['key' => 'webhook_secret', 'label' => __('Webhook-Secret'), 'type' => 'password', 'required' => false, 'help' => __('whsec_…-Secret aus dem Etsy-Webhook-Portal; ohne Eintrag läuft der Abgleich rein per Polling.')],
            ['key' => 'import_from', 'label' => __('Import ab (Datum)'), 'type' => 'text', 'required' => false, 'help' => __('Untergrenze des Erstimports (JJJJ-MM-TT); leer = die letzten 30 Tage.')],
            ['key' => 'sync_page_budget', 'label' => __('Seiten je Abgleich'), 'type' => 'number', 'required' => false, 'help' => __('Schont das Etsy-Tageslimit (10.000 Requests); der nächste Lauf holt den Rest.')],
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = PluginOrgContext::currentOrNull();
        if (! $organization instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        if (! EtsyConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded(__('Etsy ist nicht konfiguriert (Keystring/Shared Secret der Seller-App fehlen).'), 'not_configured');
        }

        $connection = EtsyConnection::query()
            ->where('organization_id', $organization->id)
            ->first();
        if (! $connection instanceof EtsyConnection || ! $connection->isActive()) {
            return PluginHealth::degraded(__('Kein Etsy-Shop verbunden — OAuth-Verbindung im Etsy-Panel herstellen.'), 'not_connected');
        }

        // Etsys Refresh-Token läuft 90 Tage nach der letzten Rotation ab;
        // ab 80 Tagen ohne Nutzung hilft nur eine Neuverbindung.
        $issued = $connection->refresh_issued_at;
        if ($issued !== null && CarbonImmutable::now()->diffInDays($issued, true) >= self::REFRESH_WARN_AFTER_DAYS) {
            return PluginHealth::degraded(__('Das Etsy-Refresh-Token ist fast abgelaufen (90-Tage-Frist) — bitte neu verbinden.'), 'reconnect_required');
        }

        try {
            $started = microtime(true);
            // Billigste Probe: das Shop-Objekt (1 Request).
            app(EtsyClientFactory::class)->for($connection)->shop((int) $connection->shop_id);

            return PluginHealth::ok(__('Etsy-API erreichbar.'))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (EtsyApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('Etsy lehnt die Zugangsdaten ab (401/403) — Keystring/Shared Secret prüfen oder neu verbinden.'), 'auth');
            }
            if ($e->isRateLimited()) {
                return PluginHealth::degraded(__('Etsy drosselt (429) — 10 Requests/Sekunde bzw. Tageslimit erreicht.'), 'rate_limited');
            }

            return PluginHealth::degraded(__('Etsy-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('Etsy-API nicht erreichbar.'), 'unreachable');
        }
    }
}
