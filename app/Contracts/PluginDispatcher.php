<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Contracts;

/**
 * Gemeinsamer Vertrag aller plugin-gebundenen Outbox-Dispatcher (C14):
 * registriert und aufgelöst über die Plugin-Kennung
 * ({@see \App\Services\AbstractPluginDispatcherResolver}).
 */
interface PluginDispatcher {
    /** Plugin-Kennung, für die dieser Dispatcher zuständig ist. */
    public function pluginId(): string;
}
