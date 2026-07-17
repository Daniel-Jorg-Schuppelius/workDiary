<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractPluginDispatcherResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PluginDispatcher;

/**
 * Generische Registry plugin-gebundener Dispatcher (C14): Plugins registrieren
 * ihren Dispatcher beim Booten; der Outbox-Job löst über die Plugin-Kennung
 * auf. Konkrete Resolver müssen als Singleton gebunden sein, damit
 * Registrierung und Auflösung dieselbe Instanz teilen.
 *
 * @template TDispatcher of PluginDispatcher
 */
abstract class AbstractPluginDispatcherResolver {
    /** @var array<string, TDispatcher> */
    private array $dispatchers = [];

    /** @param TDispatcher $dispatcher */
    public function register(PluginDispatcher $dispatcher): void {
        $this->dispatchers[$dispatcher->pluginId()] = $dispatcher;
    }

    /** @return TDispatcher|null */
    public function for(?string $pluginId): ?PluginDispatcher {
        if ($pluginId === null || $pluginId === '') {
            return null;
        }

        return $this->dispatchers[$pluginId] ?? null;
    }
}
