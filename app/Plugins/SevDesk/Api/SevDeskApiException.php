<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Api;

use RuntimeException;

/**
 * Fehler der sevDesk-REST-API (MVP-125). Die Message trägt nur Status und
 * gekürzten Body-Auszug — nie den API-Token.
 */
class SevDeskApiException extends RuntimeException {
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly ?string $endpoint = null,
    ) {
        parent::__construct($message);
    }

    public function isAuthError(): bool {
        return in_array($this->status, [401, 403], true);
    }

    public function isRateLimited(): bool {
        return $this->status === 429;
    }
}
