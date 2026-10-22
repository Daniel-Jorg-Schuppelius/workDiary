<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyDeletionBlockedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Kunde oder Lieferant trägt noch Belege, die beim Löschen per Fremdschlüssel mitgelöscht würden. */
final class PartyDeletionBlockedException extends RuntimeException {
    /** @param list<string> $blockers Bezeichnungen der vorhandenen Belegarten */
    public function __construct(public readonly array $blockers) {
        parent::__construct((string) __('Löschen nicht möglich: Es hängen noch Belege daran (:list). Bitte stattdessen archivieren.', [
            'list' => implode(', ', $blockers),
        ]));
    }
}
