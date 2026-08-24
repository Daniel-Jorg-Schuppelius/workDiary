<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Services\Notification\NotificationDispatcher;

/**
 * Ein Fristen-Scan eines Fachmoduls (Vollscan 2026-08, B11): findet fällige/
 * überfällige Sätze und feuert NotificationEvents über den zentralen
 * Dispatcher. Registriert in der {@see DeadlineScanRegistry}; ausgeführt von
 * notifications:scan-deadlines. Idempotent über das notification_dispatch_log.
 */
interface DeadlineScan {
    /** Stabiler Kurzname (Verbose-Ausgabe des Commands). */
    public function key(): string;

    /** @return int Anzahl versendeter Benachrichtigungen */
    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int;
}
