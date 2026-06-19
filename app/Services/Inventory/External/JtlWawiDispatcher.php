<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory\External;

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Models\InventoryOutboxEntry;
use RuntimeException;

/**
 * JTL-Wawi-Outbox-Dispatcher (Feature 048, MVP-073) – SCAFFOLD.
 *
 * Registriert sich (sobald aktiviert) unter {@see JtlWawiInventoryProvider::PLUGIN_ID}
 * beim {@see \App\Services\Inventory\ExternalInventoryDispatcherResolver}. Die
 * eigentliche Zustellung an die JTL-Wawi-API steht unter Pilot aus; bis dahin
 * wirft `dispatch()` bewusst – die Outbox wiederholt und markiert nach
 * Aufbrauchen der Versuche kompensationspflichtig (kein stiller Datenverlust).
 * Der Dispatcher ist absichtlich NICHT automatisch registriert.
 */
class JtlWawiDispatcher implements ExternalInventoryDispatcher {
    public function pluginId(): string {
        return JtlWawiInventoryProvider::PLUGIN_ID;
    }

    public function dispatch(InventoryOutboxEntry $entry): bool {
        // TODO(MVP-073): idempotente Zustellung der Operation $entry->operation
        // mit $entry->payload an die JTL-Wawi-API; bei Erfolg true zurückgeben.
        throw new RuntimeException('JTL-Wawi-Zustellung: API-Implementierung steht unter Pilot aus (MVP-073).');
    }
}
