<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteReplied.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Communication;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Auf eine eingegangene E-Mail-Notiz wurde aus WorkDiary geantwortet; die Antwort liegt als ausgehende Notiz vor. Nach Commit. */
final class CommunicationNoteReplied implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $noteId,
        public readonly int $replyNoteId,
        public readonly int $userId,
    ) {}
}
