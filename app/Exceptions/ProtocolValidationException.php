<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolValidationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

/**
 * Signalisiert, dass ein Protokoll- oder Item-Schritt seine
 * Validierungspflichten nicht erfuellt (MVP-021 §4).
 */
class ProtocolValidationException extends RuntimeException {
    /** @var list<string> */
    private array $errors;

    /**
     * @param  list<string>  $errors
     */
    public function __construct(array $errors, string $message = 'Protokoll-Validierung fehlgeschlagen.') {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * @return list<string>
     */
    public function errors(): array {
        return $this->errors;
    }
}
