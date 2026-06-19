<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInventoryDispatcherResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\ExternalInventoryDispatcher;

/**
 * Registry der externen Bestands-Dispatcher (Feature 048, MVP-072). Plugins
 * registrieren ihren Dispatcher beim Booten; der Outbox-Job löst über die
 * Plugin-Kennung auf. Muss als Singleton gebunden sein, damit Registrierung und
 * Auflösung dieselbe Instanz teilen.
 */
class ExternalInventoryDispatcherResolver {
    /** @var array<string, ExternalInventoryDispatcher> */
    private array $dispatchers = [];

    public function register(ExternalInventoryDispatcher $dispatcher): void {
        $this->dispatchers[$dispatcher->pluginId()] = $dispatcher;
    }

    public function for(?string $pluginId): ?ExternalInventoryDispatcher {
        if ($pluginId === null || $pluginId === '') {
            return null;
        }

        return $this->dispatchers[$pluginId] ?? null;
    }
}
