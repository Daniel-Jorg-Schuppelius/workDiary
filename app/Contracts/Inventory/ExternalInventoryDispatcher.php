<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInventoryDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Models\InventoryOutboxEntry;

/**
 * Stellt einen Outbox-Eintrag an das externe Bestandssystem zu (Feature 048,
 * MVP-072/073). Ein Plugin (z. B. JTL-Wawi) registriert seinen Dispatcher beim
 * {@see \App\Services\Inventory\ExternalInventoryDispatcherResolver}.
 */
interface ExternalInventoryDispatcher {
    /** Plugin-Kennung, für die dieser Dispatcher zuständig ist. */
    public function pluginId(): string;

    /**
     * Wendet die Bewegung extern an. Muss idempotent gegenüber dem
     * `idempotency_key` des Eintrags sein.
     *
     * @return bool true, wenn extern bestätigt (Confirmed)
     *
     * @throws \RuntimeException bei vorübergehendem Fehler (Queue wiederholt)
     */
    public function dispatch(InventoryOutboxEntry $entry): bool;
}
