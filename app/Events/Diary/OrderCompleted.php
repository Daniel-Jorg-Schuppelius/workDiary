<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderCompleted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Diary;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Auftrag abgeschlossen (MVP-1293) — Folgeprozesse wie die Kundenumfrage. */
final class OrderCompleted implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $diaryEntryId,
    ) {}
}
