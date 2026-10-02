<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellingPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\DomainReselling;

use App\Enums\Domain\DomainConnectionStatus;
use App\Models\Domain\DomainProviderConnection;
use App\Models\Platform\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\Domain\{DomainProviderAdapter, DomainRegistrarSettings};
use App\Plugins\Contracts\{DomainRegistrar, Plugin, PluginCapability, SettingsField};
use App\Plugins\DomainReselling\Adapters\DomainResellingAdapter;
use App\Plugins\DomainReselling\Api\DomainResellingClient;
use App\Plugins\Support\PluginOrgContext;
use Throwable;

/**
 * DomainReselling-Plugin (Feature 083): projiziert und verwaltet Domains eines
 * DomainReselling-Reseller-/Registrar-Kontos kontrolliert. Kündigt die
 * Fähigkeit {@see PluginCapability::DomainRegistrar} an; die App-Services
 * lösen darüber den {@see DomainProviderAdapter} je Verbindung auf.
 *
 * Kein installationsweiter App-Key: angebunden wird je Verbindung mit Login
 * und verschlüsseltem Passwort. Der Adapter bleibt „Pilot offen", bis ein
 * realer OT&E-/Produktivpilot bestanden ist (MVP-384/396).
 */
class DomainResellingPlugin extends AbstractPlugin implements DomainRegistrar {
    public const ID = 'domainreselling';

    /** Von der Plugin-Discovery VOR der Instanziierung registriert. */
    public const SERVICE_PROVIDER = DomainResellingServiceProvider::class;

    public function name(): string {
        return 'DomainReselling';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('domainreselling::domain.plugin.description');
    }

    /** @return array<int, PluginCapability> */
    public function capabilities(): array {
        return [PluginCapability::DomainRegistrar];
    }

    public function domainAdapter(DomainProviderConnection $connection): DomainProviderAdapter {
        return new DomainResellingAdapter(new DomainResellingClient($connection), $connection);
    }

    public function domainSettings(int $organizationId): DomainRegistrarSettings {
        $config = DomainResellingConfig::resolve($organizationId);

        return new DomainRegistrarSettings($config['check_budget_per_hour'], $config['check_cache_ttl'], $config['list_page_size'], $config['stale_after_hours']);
    }

    /** Bounds der numerischen Betriebsparameter (key => [min, max]). */
    private const BOUNDS = [
        'timeout' => [5, 120],
        'check_budget_per_hour' => [1, 5000],
        'check_cache_ttl' => [10, 86400],
        'list_page_size' => [10, 1000],
        'stale_after_hours' => [1, 720],
    ];

    public function adminPanel(): ?array {
        // Verbindungen laufen über die zentralen App-Seiten (admin.domain-provider.*,
        // bereits in der Navigation); die Betriebsparameter über den Plugin-Dialog.
        return null;
    }

    /**
     * Betriebsparameter je Organisation (Fallback: config/ENV). Zugangsdaten
     * liegen weiterhin je Verbindung, nicht in plugin_settings; die
     * Endpoint-Allowlist ist bewusst NICHT konfigurierbar.
     */
    public function settingsSchema(): array {
        return [
            SettingsField::number('timeout', __('domainreselling::domain.settings.timeout'),
                default: (string) config('plugins.domainreselling.timeout', 20),
                help: __('domainreselling::domain.settings.timeout_help'))->toArray(),
            SettingsField::number('check_budget_per_hour', __('domainreselling::domain.settings.check_budget_per_hour'),
                default: (string) config('plugins.domainreselling.check_budget_per_hour', 300),
                help: __('domainreselling::domain.settings.check_budget_per_hour_help'))->toArray(),
            SettingsField::number('check_cache_ttl', __('domainreselling::domain.settings.check_cache_ttl'),
                default: (string) config('plugins.domainreselling.check_cache_ttl', 300),
                help: __('domainreselling::domain.settings.check_cache_ttl_help'))->toArray(),
            SettingsField::number('list_page_size', __('domainreselling::domain.settings.list_page_size'),
                default: (string) config('plugins.domainreselling.list_page_size', 100),
                help: __('domainreselling::domain.settings.list_page_size_help'))->toArray(),
            SettingsField::number('stale_after_hours', __('domainreselling::domain.settings.stale_after_hours'),
                default: (string) config('plugins.domainreselling.stale_after_hours', 24),
                help: __('domainreselling::domain.settings.stale_after_hours_help'))->toArray(),
        ];
    }

    /**
     * Validiert die Betriebsparameter: ganze Zahl im jeweiligen Bereich.
     * Leere/fehlende Felder sind gültig (Fallback auf den config()-Default).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public function validateSettings(array $settings): array {
        $errors = [];
        foreach (self::BOUNDS as $key => [$min, $max]) {
            if (! array_key_exists($key, $settings)) {
                continue;
            }
            $value = filter_var($settings[$key], FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                $errors[$key] = __('domainreselling::domain.settings.range_error', ['min' => $min, 'max' => $max]);
            }
        }

        return $errors;
    }

    /** Eigenes Partial: Zugangsdaten-Hinweis + Standard-Felder. */
    public function settingsView(): ?string {
        return 'domainreselling::settings';
    }

    /** Health je Organisation: Zustand der DomainReselling-Verbindungen. */
    public function healthCheck(): PluginHealth {
        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('domainreselling::domain.health.no_org_context'));
        }

        try {
            $blocked = DomainProviderConnection::query()
                ->where('organization_id', $org->id)
                ->where('status', DomainConnectionStatus::Blocked->value)
                ->exists();

            if ($blocked) {
                return PluginHealth::degraded(__('domain.health.attention'));
            }

            // Aktive, aber noch nicht pilotbestätigte Verbindung sichtbar melden.
            $pilotOpen = DomainProviderConnection::query()
                ->where('organization_id', $org->id)
                ->where('status', DomainConnectionStatus::Active->value)
                ->whereNull('pilot_confirmed_at')
                ->exists();

            return $pilotOpen
                ? PluginHealth::degraded(__('domainreselling::domain.health.pilot_open'))
                : PluginHealth::ok(__('domainreselling::domain.health.ok'));
        } catch (Throwable $e) {
            return PluginHealth::failing(__('domainreselling::domain.health.error', ['class' => class_basename($e)]));
        }
    }
}
