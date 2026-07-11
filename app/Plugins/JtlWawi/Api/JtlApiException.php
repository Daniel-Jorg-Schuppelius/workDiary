<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Api;

use RuntimeException;

/**
 * Fehlerantwort der JTL-Wawi-API (Feature 078). Trägt HTTP-Status und den
 * JTL-eigenen `errorCode` (kein RFC 7807). Die Message enthält nie Secrets
 * oder vollständige Payloads — nur Status, Code und Endpunkt-Kurzform.
 */
class JtlApiException extends RuntimeException {
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }

    public function isAuthError(): bool {
        return in_array($this->status, [401, 403], true);
    }

    public function isMissingEndpoint(): bool {
        return in_array($this->status, [404, 405], true);
    }
}
