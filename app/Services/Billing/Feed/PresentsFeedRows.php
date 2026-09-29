<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresentsFeedRows.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

use App\Models\Platform\User;
use App\Support\Ui\UiAction;

/**
 * Plugin-Quellen des Belegfluss stellen ihre Zeilen selbst dar (MVP-1041):
 * Ziel der Belegnummer und Zeilenaktionen samt eigener Rechteprüfung. Kern-
 * Zeilen (Rechnung, Angebot, E-Rechnung, Auslage) bleiben im View.
 */
interface PresentsFeedRows {
    /** Gehört die Feed-Zeile zu dieser Quelle? (Zeilenart allein reicht nicht: `voucher` liefern mehrere.) */
    public function presents(\stdClass $row): bool;

    /** Ziel der Belegnummer; null = kein Ziel. */
    public function rowLink(\stdClass $row, User $user): ?UiAction;

    /** @return list<UiAction> Aktionen am Zeilenende, z. B. Mahnung */
    public function rowActions(\stdClass $row, User $user, bool $overdue): array;
}
