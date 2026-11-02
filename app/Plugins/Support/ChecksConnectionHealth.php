<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksConnectionHealth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Plugins\PluginHealth;
use App\Plugins\Support\Contracts\ReadyConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Healthcheck der Plugins mit Zugangsdaten je Organisation und Leseprobe
 * gegen eine Fremd-API (Aufgabenimporte, Shops, Buchhaltungen der Phase 158;
 * Konsolidierung kon-04): Einstellungen vollständig, Verbindung geprüft,
 * dann die Probe über {@see ApiHealthProbe}. Die Meldungen liest das Trait
 * aus `<id>::<id>.health.not_configured` und `.not_connected` des Plugins.
 * Gegenstück für Carrier: {@see ChecksCarrierHealth}.
 */
trait ChecksConnectionHealth {
    /** Einstellungen der Organisation vollständig? (Installationsweite Apps wie Jira ignorieren die Organisation.) */
    abstract protected function healthConfigured(int $organizationId): bool;

    /** Leseprobe gegen die Instanz; wirft bei Fehlern, die {@see ApiHealthProbe} einordnet. */
    abstract protected function healthProbe(int $organizationId): void;

    /**
     * Verbindungszeile je Organisation; null für Plugins, die nur über die
     * Einstellungen angebunden sind (Akaunting, Invoice Ninja).
     *
     * @return class-string<Model&ReadyConnection>|null
     */
    protected function healthConnectionModel(): ?string {
        return null;
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        $organizationId = (int) $organization->id;
        if (! $this->healthConfigured($organizationId)) {
            return PluginHealth::degraded($this->healthText('not_configured'), 'not_configured');
        }
        $model = $this->healthConnectionModel();
        if ($model !== null) {
            $connection = $model::query()->where('organization_id', $organizationId)->first();
            if (! $connection instanceof ReadyConnection || ! $connection->isReady()) {
                return PluginHealth::degraded($this->healthText('not_connected'), 'not_connected');
            }
        }

        return ApiHealthProbe::probe($this->name(), function () use ($organizationId): void {
            $this->healthProbe($organizationId);
        });
    }

    private function healthText(string $key): string {
        return (string) __(static::ID . '::' . static::ID . '.health.' . $key);
    }
}
