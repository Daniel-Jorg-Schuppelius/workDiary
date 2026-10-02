<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlinePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline;

use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\SettingsField;
use App\Plugins\DatevOnline\Models\DatevOnlineConnection;

/**
 * DATEV-Online (MVP-122): Anmeldung über „Login mit DATEV“ (OAuth mit PKCE),
 * Mandantenwahl, abgeschlossene Buchungsstapel per EXTF-Import statt Download,
 * nächtlicher Upload der Belegbilder (Ausgangs- und Eingangsrechnungen) in
 * DATEV Unternehmen online. Der EXTF-Dateiweg im Kern bleibt unverändert.
 */
class DatevOnlinePlugin extends AbstractPlugin {
    public const ID = 'datev-online';

    public const SERVICE_PROVIDER = DatevOnlineServiceProvider::class;

    public function name(): string {
        return 'DATEV-Online';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('datev-online::datev.plugin.description');
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.datev-online.index',
            'label' => 'DATEV-Online',
            'icon' => 'account_balance',
        ];
    }

    public function settingsSchema(): array {
        return [
            SettingsField::text('client_id', (string) __('datev-online::datev.settings.client_id'), required: true,
                help: (string) __('datev-online::datev.settings.client_id_help', ['url' => route('admin.datev-online.oauth.callback')]))->toArray(),
            SettingsField::password('client_secret', (string) __('datev-online::datev.settings.client_secret'), required: true)->toArray(),
            SettingsField::boolean('sandbox', (string) __('datev-online::datev.settings.sandbox'), default: true,
                help: (string) __('datev-online::datev.settings.sandbox_help'))->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! DatevOnlineConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('datev-online::datev.health.not_configured'), code: 'not_configured');
        }
        $connection = DatevOnlineConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof DatevOnlineConnection || ! $connection->isActive()) {
            return PluginHealth::degraded((string) __('datev-online::datev.health.not_connected'), code: 'not_connected');
        }
        if ($connection->datev_client_number === null) {
            return PluginHealth::degraded((string) __('datev-online::datev.health.no_client'), code: 'no_client');
        }
        if ($connection->last_error !== null) {
            return PluginHealth::degraded((string) __('datev-online::datev.health.last_error', ['error' => $connection->last_error]), code: 'last_error');
        }

        return PluginHealth::ok((string) __('datev-online::datev.health.ok', ['client' => (string) $connection->datev_client_name]));
    }
}
