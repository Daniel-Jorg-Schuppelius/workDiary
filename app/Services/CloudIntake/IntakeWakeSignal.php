<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeWakeSignal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\CloudIntake;

use Illuminate\Support\Facades\Cache;

/**
 * Aufwecksignale der Provider-Webhooks (Feature 080): ein Cache-Flag je
 * Verbindung, das der Scheduler-Lauf (P9) konsumiert und dann den regulären
 * Cursor-Lauf vorzieht. Bewusst verlusttolerant — geht ein Signal verloren,
 * greift das periodische Delta-Polling.
 */
class IntakeWakeSignal {
    private const TTL_SECONDS = 3600;

    public function signal(int $connectionId): void {
        Cache::put($this->key($connectionId), true, self::TTL_SECONDS);
    }

    public function consume(int $connectionId): bool {
        return (bool) Cache::pull($this->key($connectionId), false);
    }

    private function key(int $connectionId): string {
        return 'cloud-intake:wake:' . $connectionId;
    }
}
