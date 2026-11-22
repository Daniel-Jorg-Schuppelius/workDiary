<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClosureLockMemo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval;

/**
 * Sperrstand von Monat und Tag je Person für die Dauer eines Requests bzw.
 * Jobs (scoped gebunden). Zeitlisten prüfen die Sperre je Zeile; ohne Merker
 * sind das drei Abfragen je Eintrag. `MonthClosure` und `DayClosure`
 * entwerten beim Speichern und Löschen.
 */
final class ClosureLockMemo {
    /** @var array<string, bool> */
    private array $locks = [];

    /** @param  \Closure(): bool  $resolve */
    public function remember(string $key, \Closure $resolve): bool {
        return $this->locks[$key] ??= $resolve();
    }

    public function flush(): void {
        $this->locks = [];
    }
}
