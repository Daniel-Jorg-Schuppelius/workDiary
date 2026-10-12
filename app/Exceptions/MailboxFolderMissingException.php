<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxFolderMissingException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ein eingestellter Postfach-Ordner existiert nicht (MVP-1140). Beim
 * Abruf-Ordner bricht der Lauf ab, beim Verarbeitet-Ordner bleibt die Mail
 * liegen und der Lauf meldet es.
 */
final class MailboxFolderMissingException extends RuntimeException {
    public function __construct(public readonly string $folder) {
        parent::__construct((string) __('mail.error.folder_missing', ['folder' => $folder]));
    }
}
