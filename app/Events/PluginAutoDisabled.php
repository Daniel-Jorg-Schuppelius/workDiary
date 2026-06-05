<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginAutoDisabled.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ein Plugin wurde nach wiederholten Fehlern automatisch stillgelegt
 * (je Organisation; `organizationId = null` = global).
 */
class PluginAutoDisabled {
    use Dispatchable;

    public function __construct(
        public readonly string $pluginId,
        public readonly ?int $organizationId,
        public readonly string $reason,
        public readonly int $failureCount,
    ) {}
}
