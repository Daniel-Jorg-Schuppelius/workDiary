<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNumberTakenException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Eine Kunden- bzw. Lieferantennummer gehört schon einem anderen Kontakt —
 * lokal oder im Buchhaltungsprogramm (MVP-1132/1133).
 */
final class ContactNumberTakenException extends RuntimeException {
    public static function local(string $number, string $holder): self {
        return new self((string) __('numbering.error.taken_local', ['number' => $number, 'holder' => $holder]));
    }

    public static function remote(string $number, string $program, string $name): self {
        return new self((string) __('numbering.error.taken_remote', ['number' => $number, 'program' => $program, 'name' => $name]));
    }
}
