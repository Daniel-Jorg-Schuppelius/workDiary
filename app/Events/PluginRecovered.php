<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginRecovered.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ein Plugin (je Organisation) ist nach einem nicht-OK-Zustand wieder gesund.
 * `organizationId = null` = globales Plugin.
 */
class PluginRecovered {
    use Dispatchable;

    public function __construct(
        public readonly string $pluginId,
        public readonly ?int $organizationId,
        public readonly string $message = '',
    ) {}
}
