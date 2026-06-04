<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRateLimitException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use RuntimeException;

/**
 * Lexoffice hat das Anfragelimit (2 Anfragen/Sekunde) erreicht und mit
 * HTTP 429 geantwortet. Anders als ein 401/403 ist das ein *transienter*
 * Zustand: Der Aufrufer (z. B. der Healthcheck) soll daraus `degraded`
 * ableiten und das Plugin NICHT als dauerhaft defekt einstufen.
 */
class LexofficeRateLimitException extends RuntimeException {
    public function __construct(string $message = '') {
        parent::__construct(
            $message !== '' ? $message
                : (string) __('Lexoffice hat das Anfragelimit erreicht (429). Bitte versuche es in einigen Minuten erneut.'),
        );
    }
}
