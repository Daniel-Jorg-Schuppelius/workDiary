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

use App\Models\{CarrierConnection, Organization};
use App\Plugins\PluginHealth;
use Throwable;

/**
 * Gemeinsames healthy()/healthCheck()-Paar der Carrier-Plugins (W3d) —
 * ersetzt drei wortgleiche Kopien in DHL/FedEx/UPS. Die nutzende Klasse
 * liefert den Carrier-Schlüssel über ihre `CARRIER`-Konstante, den Ping über
 * {@see carrierPing()} und die Meldungen über {@see carrierHealthMessages()} /
 * {@see carrierErrorMessage()} — die Lang-Keys existieren nur je Carrier
 * wörtlich, daher keine :carrier-Parametrisierung (Verhaltensneutralität).
 */
trait ChecksCarrierHealth {
    /** Führt den Carrier-Ping mit der Anbindung aus (wirft bei Transportfehlern). */
    abstract protected function carrierPing(CarrierConnection $connection): bool;

    /**
     * Wörtliche (je Carrier eigenständig übersetzte) Health-Meldungen.
     *
     * @return array{missing: string, disabled: string, connected: string, unreachable: string}
     */
    abstract protected function carrierHealthMessages(): array;

    /** Meldung für unerwartete Ausnahmen im Health-Check (mit Exception-Kurzname). */
    abstract protected function carrierErrorMessage(Throwable $e): string;

    public function healthy(CarrierConnection $connection): bool {
        try {
            return $this->carrierPing($connection);
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

        try {
            return $this->healthy($connection)
                ? PluginHealth::ok($messages['connected'])
                : PluginHealth::failing($messages['unreachable'], 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing($this->carrierErrorMessage($e));
        }
    }
}
