<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeApiBusyException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Exceptions;

use RuntimeException;

/**
 * Ein anderer Lauf (Sync-Kommando, Job) hält die API-Sperre der Organisation.
 * Transient wie {@see LexofficeRateLimitException}: der Aufrufer weicht aus,
 * der Nachlauf holt die Übertragung nach.
 */
class LexofficeApiBusyException extends RuntimeException {
    public function __construct(string $message = '') {
        parent::__construct(
            $message !== '' ? $message
                : (string) __('Lexoffice wird gerade von einem anderen Lauf abgeglichen. Bitte versuchen Sie es in einigen Minuten erneut.'),
        );
    }
}
