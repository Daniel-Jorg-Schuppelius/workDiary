<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMasterdataException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\OpenMasterdata\Exceptions;

use RuntimeException;

/** Abfrage beim Großhändler nicht möglich; `reason` ist der Textschlüssel unter `procurement.omd.error`. */
final class OpenMasterdataException extends RuntimeException {
    public const NOT_CONFIGURED = 'not_configured';

    public const NOT_FOUND = 'not_found';

    public const AMBIGUOUS = 'ambiguous';

    /** HTTP 950/951 der Fassung 1.x: Alternativ- oder Nachfolgeartikel, dessen Antwort der HTTP-Stapel nicht liest. */
    public const REPLACED = 'replaced';

    public const UNAUTHORIZED = 'unauthorized';

    public const RATE_LIMITED = 'rate_limited';

    public const INVALID_RESPONSE = 'invalid_response';

    public const FAILED = 'failed';

    public function __construct(public readonly string $reason, public readonly ?int $status = null) {
        parent::__construct('Open Masterdata: ' . $reason . ($status !== null ? ' (HTTP ' . $status . ')' : ''));
    }

    /** Zugangsdaten abgelehnt — ein Sammellauf bricht hier ab, statt es je Artikel erneut zu versuchen. */
    public function isAuthFailure(): bool {
        return $this->reason === self::UNAUTHORIZED || $this->reason === self::NOT_CONFIGURED;
    }
}
