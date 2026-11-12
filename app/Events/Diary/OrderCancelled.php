<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderCancelled.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Diary;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Auftrag storniert (MVP-1596). Synchron in der Transaktion des Stornos: Protokolle
 * und Prozedurläufe des Auftrags schließen mit ihm oder gar nicht.
 */
final class OrderCancelled {
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $diaryEntryId,
        public readonly ?int $actorUserId,
        public readonly string $reason,
    ) {}
}
