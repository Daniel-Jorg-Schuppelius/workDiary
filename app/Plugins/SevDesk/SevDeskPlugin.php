<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk;

use App\Models\{Organization, PluginSetting};
use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\SevDesk\Api\{SevDeskApiException, SevDeskClient, SevDeskClientFactory};
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * sevDesk-Plugin (MVP-125, Bauturbo A4): drittes API-Faktura-Ziel neben
 * Lexoffice und orgaMAX.
 *
 * - Auth: benutzergebundener API-Token (ohne Scopes) gegen
 *   https://my.sevdesk.de/api/v1 — verschlüsselt je Organisation in
 *   plugin_settings (Auto-Form in der Plugin-Karte, kein eigener
 *   Verbindungsfluss wie beim orgaMAX-iid-Callback nötig).
 * - Faktura-Übergabe über {@see \App\Services\Finance\Targets\SevDeskTarget}
 *   ({@see \App\Enums\Finance\TransferTarget::SevDesk}): sevDesk führt die
 *   Rechnung (Entwurf, Status 50) — keine parallele lokale Fakturierung,
 *   `enshrine` wird nie aufgerufen.
 * - „Update 2.0" ist Buchhaltungslogik je Account: der Healthcheck probt
 *   GET /Tools/bookkeepingSystemVersion und erneuert den Versions-Cache
 *   des Mandanten.
 */
class SevDeskPlugin implements Plugin {
    use PluginDefaults;

    public const ID = 'sevdesk';

    public const SERVICE_PROVIDER = SevDeskServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'sevDesk';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Übergibt bestätigte Abrechnungspositionen als Rechnungsentwurf an sevDesk (API-Token gegen my.sevdesk.de): Kontakt-Projektion, idempotente Übergabe mit Quellmarker, Erkennung der Buchhaltungs-Version 1.0/2.0 je Mandant.');
    }

    public function isEnabled(): bool {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if ($organization instanceof Organization) {
            $setting = PluginSetting::forOrganization($organization->id, self::ID);
            if ($setting->exists) {
                return (bool) $setting->enabled;
            }
        }

        return (bool) config('plugins.' . self::ID . '.enabled', false);
    }

    /** @return array<int, \App\Plugins\Contracts\PluginCapability> Fähigkeiten hängen am FacturationTarget-Vertrag. */
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
            ['key' => 'api_key', 'label' => __('API-Token'), 'type' => 'password', 'required' => true, 'help' => __('Benutzergebundener API-Token aus dem sevDesk-Account (Einstellungen → Benutzer).')],
            ['key' => 'base_url', 'label' => __('API-Basis-URL'), 'type' => 'text', 'default' => 'https://my.sevdesk.de/api/v1'],
            ['key' => 'default_vat_rate', 'label' => __('Standard-USt %'), 'type' => 'text', 'default' => '19'],
        ];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /**
     * Token-Wechsel invalidiert den Versions-Cache des Mandanten.
     *
     * @param array<string, mixed> $settings
     */
    public function onSettingsSaved(int $organizationId, array $settings): void {
        Cache::forget(SevDeskClient::versionCacheKey($organizationId));
    }

    public function healthCheck(): PluginHealth {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $organization instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        $config = SevDeskConfig::resolve((int) $organization->id);
        if (empty($config['api_key'])) {
            return PluginHealth::degraded(__('sevDesk ist nicht konfiguriert (API-Token fehlt).'), 'not_configured');
        }

        try {
            $started = microtime(true);
            // Probe = dokumentierter Versions-Endpunkt; erneuert zugleich den
            // je Mandant gecachten bookkeepingSystemVersion-Wert.
            $version = app(SevDeskClientFactory::class)
                ->for((int) $organization->id)
                ->bookkeepingVersion(fresh: true);

            return PluginHealth::ok(__('sevDesk-API erreichbar (Buchhaltung :version).', ['version' => $version]))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (SevDeskApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('sevDesk lehnt den API-Token ab (401) — Token prüfen.'), 'auth');
            }

            return PluginHealth::degraded(__('sevDesk-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('sevDesk-API nicht erreichbar.'), 'unreachable');
        }
    }
}
