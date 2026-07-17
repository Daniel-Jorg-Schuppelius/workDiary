<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Contracts\Integration;

use App\Contracts\PluginDispatcher;
use App\Models\IntegrationOutboxEntry;

/**
 * Stellt einen generischen Outbox-Eintrag an das externe System zu
 * (Feature 055, MVP-114). Ein Plugin registriert seinen Dispatcher beim
 * {@see \App\Services\Integration\IntegrationOutboxDispatcherResolver}
 * — Gegenstück zum Bestands-Vertrag
 * {@see \App\Contracts\Inventory\ExternalInventoryDispatcher}.
 */
interface IntegrationOutboxDispatcher extends PluginDispatcher {
    /**
     * Wendet die Operation extern an. Muss idempotent gegenüber dem
     * `idempotency_key` des Eintrags sein.
     *
     * @return bool true, wenn extern bestätigt (Confirmed)
     *
     * @throws \RuntimeException bei vorübergehendem Fehler (Queue wiederholt)
     */
    public function dispatch(IntegrationOutboxEntry $entry): bool;
}
