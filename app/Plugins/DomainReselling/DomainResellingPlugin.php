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
use App\Models\{Organization, PluginSetting};
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Plugins\Contracts\{DomainRegistrar, Plugin, PluginCapability};
use App\Plugins\DomainReselling\Adapters\DomainResellingAdapter;
use App\Plugins\DomainReselling\Api\DomainResellingClient;
use App\Plugins\{PluginDefaults, PluginHealth};
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
class DomainResellingPlugin implements DomainRegistrar, Plugin {
    use PluginDefaults;

    public const ID = 'domainreselling';

    /** Von der Plugin-Discovery VOR der Instanziierung registriert. */
    public const SERVICE_PROVIDER = DomainResellingServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'DomainReselling';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('domain.plugin.description');
    }

    public function isEnabled(): bool {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                $row = PluginSetting::forOrganization($org->id, self::ID);
                if ($row->exists) {
                    return $row->enabled;
                }
            }
        }

        return (bool) config('plugins.domainreselling.enabled', false);
    }

    /** @return array<int, PluginCapability> */
    public function capabilities(): array {
        return [PluginCapability::DomainRegistrar];
    }

    public function domainAdapter(DomainProviderConnection $connection): DomainProviderAdapter {
        return new DomainResellingAdapter(new DomainResellingClient($connection), $connection);
    }

    public function adminPanel(): ?array {
        // Verwaltung läuft über die zentralen App-Seiten (admin.domain-provider.*).
        return null;
    }

    public function serviceProvider(): ?string {
        return DomainResellingServiceProvider::class;
    }

    /** Zugangsdaten liegen je Verbindung, nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health je Organisation: Zustand der DomainReselling-Verbindungen. */
    public function healthCheck(): PluginHealth {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('domain.health.no_org_context'));
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
                ? PluginHealth::degraded(__('domain.health.pilot_open'))
                : PluginHealth::ok(__('domain.health.ok'));
        } catch (Throwable $e) {
            return PluginHealth::failing(__('domain.health.error', ['class' => class_basename($e)]));
        }
    }
}
