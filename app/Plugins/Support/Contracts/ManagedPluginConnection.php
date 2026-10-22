<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagedPluginConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Contracts;

use App\Models\Audit\AuditLog;

/** Verbindung eines Plugins je Organisation, die der Admin-Bereich anstößt und trennt. */
interface ManagedPluginConnection {
    public function isActive(): bool;

    /** @param array<string, mixed> $changes */
    public function audit(string $event, array $changes = []): AuditLog;
}
