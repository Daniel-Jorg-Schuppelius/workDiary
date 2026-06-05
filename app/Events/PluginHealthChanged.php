<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHealthChanged.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Der Healthcheck-Status eines Plugins (je Organisation) hat sich geändert.
 * `organizationId = null` = globales Plugin.
 */
class PluginHealthChanged {
    use Dispatchable;

    public function __construct(
        public readonly string $pluginId,
        public readonly ?int $organizationId,
        public readonly ?string $from,
        public readonly string $to,
        public readonly string $message = '',
    ) {}
}
