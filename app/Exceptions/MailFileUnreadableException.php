<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileUnreadableException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Attachments\MailFileFailure;
use RuntimeException;
use Throwable;

/**
 * Eine Mail-Datei (`.eml`/`.msg`) lässt sich nicht lesen (Feature 204). Der
 * Grund steckt im Enum; die Meldung nennt nie Inhalte der Datei.
 */
class MailFileUnreadableException extends RuntimeException {
    public function __construct(
        public readonly MailFileFailure $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Mail-Datei nicht lesbar: ' . $reason->value, 0, $previous);
    }
}
