<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxDispatcherResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Contracts\Integration\IntegrationOutboxDispatcher;

/**
 * Registry der generischen Outbox-Dispatcher (Feature 055, MVP-114). Plugins
 * registrieren ihren Dispatcher beim Booten; der Outbox-Job löst über die
 * Plugin-Kennung auf. Muss als Singleton gebunden sein, damit Registrierung
 * und Auflösung dieselbe Instanz teilen.
 */
class IntegrationOutboxDispatcherResolver {
    /** @var array<string, IntegrationOutboxDispatcher> */
    private array $dispatchers = [];

    public function register(IntegrationOutboxDispatcher $dispatcher): void {
        $this->dispatchers[$dispatcher->pluginId()] = $dispatcher;
    }

    public function for(?string $pluginId): ?IntegrationOutboxDispatcher {
        if ($pluginId === null || $pluginId === '') {
            return null;
        }

        return $this->dispatchers[$pluginId] ?? null;
    }
}
