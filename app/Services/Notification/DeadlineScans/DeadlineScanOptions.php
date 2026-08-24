<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineScanOptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use Closure;

/**
 * Laufoptionen des Fristen-Scanners: Vorlauf-Fenster aus den Command-Optionen
 * plus Konsolen-Hook für Zwischenmeldungen (z. B. Konformitäts-Verfall).
 */
final class DeadlineScanOptions {
    /** @param  (Closure(string): void)|null  $info */
    public function __construct(
        public readonly int $dueDays,
        public readonly int $expiringDays,
        private readonly ?Closure $info = null,
    ) {}

    /** Zwischenmeldung an die Konsole des aufrufenden Commands. */
    public function info(string $message): void {
        if ($this->info !== null) {
            ($this->info)($message);
        }
    }
}
