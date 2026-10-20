<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalAccessException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Fachlicher Fehlerfall beim Entfernen eines Portalzugangs (MVP-1246): der
 * Aufrufer erkennt den Fall an der Klasse, die Meldung ist bereits übersetzt.
 */
final class PortalAccessException extends RuntimeException {
    public static function hasSignedIn(): self {
        return new self((string) __('customer_portal.remove.delete_refused'));
    }

    public static function stillActive(): self {
        return new self((string) __('customer_portal.remove.anonymize_refused'));
    }
}
