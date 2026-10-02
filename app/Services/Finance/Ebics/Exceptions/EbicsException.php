<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics\Exceptions;

use RuntimeException;

/** Fehler eines EBICS-Schritts; `reason` ist der Textschlüssel unter `ebics.error`, `code` der EBICS-Rückgabecode. */
final class EbicsException extends RuntimeException {
    public function __construct(
        public readonly string $reason,
        public readonly ?string $ebicsCode = null,
        string $detail = '',
    ) {
        parent::__construct('EBICS: ' . $reason . ($ebicsCode !== null ? ' (' . $ebicsCode . ')' : '') . ($detail !== '' ? ' — ' . $detail : ''));
    }
}
