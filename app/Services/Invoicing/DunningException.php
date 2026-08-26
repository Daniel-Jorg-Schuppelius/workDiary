<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use RuntimeException;

/** Mahnung abgelehnt (MVP-691) — Grund als Code, Meldung für den Flash. */
final class DunningException extends RuntimeException {
    public const REASON_NOT_OVERDUE = 'not_overdue';

    public const REASON_MAX_LEVEL = 'max_level';

    public const REASON_BLOCKED = 'blocked';

    public const REASON_NO_EMAIL = 'no_email';

    public function __construct(public readonly string $reason, string $message) {
        parent::__construct($message);
    }
}
