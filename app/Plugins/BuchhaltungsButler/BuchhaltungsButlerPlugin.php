<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuchhaltungsButlerPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler;

use App\Models\{Organization, PluginSetting};
use App\Plugins\BuchhaltungsButler\Api\{BhbApiException, BhbClientFactory};
use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\PluginOrgContext;
use Throwable;

/**
 * BuchhaltungsButler-Plugin (MVP-432, Phase 40): Beleg-Push-Ziel — workDiary
 * bleibt Rechnungsersteller (lokale Rechnungshoheit), die Buchführung läuft
 * beim Kunden in BuchhaltungsButler. Ausgestellte lokale Rechnungen werden
 * als Belege (PDF + Strukturdaten) über die integration_outbox gepusht.
 *
 * - Auth: HTTP Basic `<Api Client>:<Api Secret>` + Pflicht-Formfeld `api_key`
 *   in jedem Request (BHB-Vertrag); alle drei Werte verschlüsselt je
 *   Organisation in plugin_settings.
 * - Die API ist beim Kunden ein tarifpflichtiges Add-on — fehlende
 *   Freischaltung ist ein erklärter Blocked-State (kein Fehler-Spam,
 *   kein Auto-Disable).
 * - Rate-Limit 100 req/Kunde/min → Request-Intervall 0,6 s.
 * - Kein Rück-Sync (Buchungen/Zahlungen) im MVP — bewusste Grenze.
 */
class BuchhaltungsButlerPlugin implements Plugin {
    use PluginDefaults;

    public const ID = 'buchhaltungsbutler';

    public const SERVICE_PROVIDER = BuchhaltungsButlerServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'BuchhaltungsButler';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Übergibt ausgestellte lokale Rechnungen als Belege an BuchhaltungsButler (Basic Auth + api_key): idempotenter Push über die Integrations-Outbox mit sha256-Nachweis; die Rechnungshoheit bleibt bei WorkDiary, die Buchführung bei BuchhaltungsButler.');
    }

    public function isEnabled(): bool {
        $organization = PluginOrgContext::currentOrNull();
        if ($organization instanceof Organization) {
            $setting = PluginSetting::forOrganization($organization->id, self::ID);
            if ($setting->exists) {
                return (bool) $setting->enabled;
            }
        }

        return (bool) config('plugins.' . self::ID . '.enabled', false);
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> Push läuft über Observer + integration_outbox. */
    public function capabilities(): array {
        return [];
    }

    /** Konfiguration läuft über die Auto-Form der Plugin-Karte (settingsSchema). */
    public function adminPanel(): ?array {
        return null;
    }

    public function serviceProvider(): ?string {
        return self::SERVICE_PROVIDER;
    }

    /** @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}> */
    public function settingsSchema(): array {
        return [
            ['key' => 'api_client', 'label' => __('API-Client'), 'type' => 'text', 'required' => true, 'help' => __('BuchhaltungsButler → Einstellungen → Schnittstellen & API-Zugang.')],
            ['key' => 'api_secret', 'label' => __('API-Secret'), 'type' => 'password', 'required' => true],
            ['key' => 'api_key', 'label' => __('API-Key (Mandant)'), 'type' => 'password', 'required' => true, 'help' => __('Wird als Formfeld api_key in jedem Request mitgesendet (BHB-Vertrag).')],
            ['key' => 'push_enabled', 'label' => __('Ausgestellte Rechnungen als Beleg pushen'), 'type' => 'boolean', 'default' => true],
        ];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    public function healthCheck(): PluginHealth {
        $organization = PluginOrgContext::currentOrNull();
        if (! $organization instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        $config = BhbConfig::resolve((int) $organization->id);
        if (empty($config['api_client']) || empty($config['api_secret']) || empty($config['api_key'])) {
            return PluginHealth::degraded(__('BuchhaltungsButler ist nicht konfiguriert (API-Client/-Secret/-Key fehlen).'), 'not_configured');
        }

        try {
            $started = microtime(true);
            app(BhbClientFactory::class)->for((int) $organization->id)->probe();

            return PluginHealth::ok(__('BuchhaltungsButler-API erreichbar.'))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (BhbApiException $e) {
            // Erklärter Blocked-State VOR der generischen Auth-Prüfung:
            // 402/403 heißt hier „API-Add-on nicht gebucht", nicht „falsche
            // Zugangsdaten" (isAuthError() fasst 401 UND 403).
            if (in_array($e->status, [402, 403], true)) {
                return PluginHealth::failing(__('Die BuchhaltungsButler-API ist für diesen Account nicht freigeschaltet (kostenpflichtiges Add-on) — im BHB-Tarif buchen.'), 'api_addon_missing');
            }
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('BuchhaltungsButler lehnt die Zugangsdaten ab (401) — API-Client/-Secret prüfen.'), 'auth');
            }

            return PluginHealth::degraded(__('BuchhaltungsButler-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('BuchhaltungsButler-API nicht erreichbar.'), 'unreachable');
        }
    }
}
