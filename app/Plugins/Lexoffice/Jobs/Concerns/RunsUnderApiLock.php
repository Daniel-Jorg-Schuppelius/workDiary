<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RunsUnderApiLock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Jobs\Concerns;

use App\Plugins\Lexoffice\LexofficeConfig;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Lexoffice-Jobs teilen sich die API-Sperre der Organisation mit den
 * Sync-Kommandos (Konsolidierungs-Audit 2026-10, k2-02) — `ShouldBeUnique`
 * schützt nur gleichartige Jobs voreinander, nicht vor dem geplanten Lauf.
 *
 * Ist die Sperre belegt, geht der Job zurück in die Queue. Das Warten ist kein
 * Fehlversuch: statt `$tries` begrenzt der Job seine Fehler über
 * `$maxExceptions` und läuft bis {@see retryUntil()}.
 */
trait RunsUnderApiLock {
    public function retryUntil(): DateTimeInterface {
        return now()->addHours(2);
    }

    /** @param  callable(): void  $run */
    private function underApiLock(int $organizationId, callable $run): void {
        try {
            Cache::lock(LexofficeConfig::apiLockKey($organizationId), 1800)->block(LexofficeConfig::API_LOCK_WAIT_SHORT, $run);
        } catch (LockTimeoutException) {
            $this->release(120);
        }
    }
}
