<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee;

use App\Models\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Billbee\Api\{BillbeeApiException, BillbeeClientFactory};
use App\Plugins\Contracts\Plugin;
use App\Plugins\Support\PluginOrgContext;
use Throwable;

/**
 * Billbee-Plugin (MVP-433/434, Phase 40): EIN Konnektor für den Marktplatz-
 * Long-Tail — Billbee bündelt Amazon, eBay, Otto, Kaufland, Shopify u. a.
 * und trägt selbst die Marktplatz-Zulassungen (insbesondere Amazons
 * PII-Audit, das workDiary bewusst nicht direkt eingeht).
 *
 * - Bestellimport Inbox-First in den Spiegel `billbee_orders` (MVP-433).
 * - Bestands-/Statusrückkanal über Provider-Registry + inventory_outbox
 *   (MVP-434, Muster JTL) — Stock-Updates sind ABSOLUT (NewQuantity).
 * - Auth: `X-Billbee-Api-Key` + Basic Auth (Billbee-Nutzer + API-Passwort);
 *   Throttle 2 req/s (429 + Retry-After macht das Toolkit).
 */
class BillbeePlugin extends AbstractPlugin {
    public const ID = 'billbee';

    public const SERVICE_PROVIDER = BillbeeServiceProvider::class;

    public function name(): string {
        return 'Billbee';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Importiert Multichannel-Bestellungen (Amazon, eBay, Otto, Kaufland, Shopify …) aus Billbee Inbox-First mit Kanalherkunft und meldet Bestände als Absolut-Updates zurück — ohne eigene Marktplatz-Zulassungen.');
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> Bestand läuft über die Provider-Registry, Orders über den Spiegel. */
    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.billbee.index',
            'label' => __('Billbee'),
            'icon' => 'storefront',
        ];
    }

    /** @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}> */
    public function settingsSchema(): array {
        return [
            ['key' => 'api_key', 'label' => __('API-Key'), 'type' => 'password', 'required' => true, 'help' => __('Billbee → Einstellungen → API; die Freischaltung erfolgt durch den Billbee-Support.')],
            ['key' => 'username', 'label' => __('Billbee-Benutzername'), 'type' => 'text', 'required' => true],
            ['key' => 'api_password', 'label' => __('API-Passwort'), 'type' => 'password', 'required' => true, 'help' => __('Das separate API-Passwort des Billbee-Nutzers (nicht das Login-Passwort).')],
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = PluginOrgContext::currentOrNull();
        if (! $organization instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        $config = BillbeeConfig::resolve((int) $organization->id);
        if (empty($config['api_key']) || empty($config['username']) || empty($config['api_password'])) {
            return PluginHealth::degraded(__('Billbee ist nicht konfiguriert (API-Key/Benutzer/API-Passwort fehlen).'), 'not_configured');
        }

        try {
            $started = microtime(true);
            // Billigste Probe: eine Bestellseite mit pageSize=1.
            app(BillbeeClientFactory::class)->for((int) $organization->id)->orders(null, 1, 1);

            return PluginHealth::ok(__('Billbee-API erreichbar.'))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (BillbeeApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('Billbee lehnt die Zugangsdaten ab (401/403) — API-Key und API-Passwort prüfen.'), 'auth');
            }
            if ($e->status === 429) {
                return PluginHealth::degraded(__('Billbee drosselt (429) — 2 Requests/Sekunde nicht überschreiten.'), 'rate_limited');
            }

            return PluginHealth::degraded(__('Billbee-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('Billbee-API nicht erreichbar.'), 'unreachable');
        }
    }
}
