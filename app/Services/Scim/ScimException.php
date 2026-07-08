<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Scim;

use RuntimeException;

/**
 * Fachlicher SCIM-Fehler (Feature 057, MVP-121) mit HTTP-Status und optionalem
 * `scimType`. Der Controller übersetzt ihn in eine SCIM-Fehlerantwort
 * ({@see ScimResponse::error()}).
 */
final class ScimException extends RuntimeException {
    public function __construct(
        public readonly int $status,
        string $detail,
        public readonly ?string $scimType = null,
    ) {
        parent::__construct($detail);
    }
}
