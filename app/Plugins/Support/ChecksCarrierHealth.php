<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksCarrierHealth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\Platform\Organization;
use App\Models\Shipping\CarrierConnection;
use App\Plugins\PluginHealth;
use Throwable;

/**
 * Gemeinsames healthy()/healthCheck()-Paar der Carrier-Plugins (W3d) —
 * ersetzt drei wortgleiche Kopien in DHL/FedEx/UPS. Die nutzende Klasse
 * liefert den Carrier-Schlüssel über ihre `CARRIER`-Konstante, die Probe über
 * {@see carrierProbe()} und die Meldungen über {@see carrierHealthMessages()};
 * die Einordnung eines Fehlschlags macht {@see ApiHealthProbe}.
 */
trait ChecksCarrierHealth {
    /** Prüft Erreichbarkeit und Zugangsdaten der Anbindung; wirft bei Fehlern. */
    abstract protected function carrierProbe(CarrierConnection $connection): void;

    /**
     * Wörtliche (je Carrier eigenständig übersetzte) Health-Meldungen.
     *
     * @return array{missing: string, disabled: string, connected: string}
     */
    abstract protected function carrierHealthMessages(): array;

    public function healthy(CarrierConnection $connection): bool {
        try {
            $this->carrierProbe($connection);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // --- Plugin-Health ----------------------------------------------------

    public function healthCheck(): PluginHealth {
        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        /** @var CarrierConnection|null $connection */
        $connection = CarrierConnection::query()
            ->where('organization_id', $org->id)
            ->where('carrier', static::CARRIER)
            ->first();

        $messages = $this->carrierHealthMessages();

        if (! $connection instanceof CarrierConnection) {
            return PluginHealth::degraded($messages['missing']);
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded($messages['disabled']);
        }

        return ApiHealthProbe::probe($this->name(), function () use ($connection, $messages): string {
            $this->carrierProbe($connection);

            return $messages['connected'];
        });
    }
}
