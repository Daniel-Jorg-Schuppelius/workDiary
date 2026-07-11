<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Api;

use RuntimeException;

/**
 * Fehler der orgaMAX-OpenAPI (Feature 077). Die Message trägt nur Status und
 * gekürzten Body-Auszug — nie API-Key, Secret, ownershipId oder Token.
 */
class OrgaMaxApiException extends RuntimeException {
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

    /** Timeout/Netzfehler ohne Antwort: Ausgang der Schreiboperation unklar. */
    public function isOutcomeUnclear(): bool {
        return $this->status === 0;
    }
}
