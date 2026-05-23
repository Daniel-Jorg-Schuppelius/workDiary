<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetValidationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

class AssetValidationException extends RuntimeException {
    public const CODE_INVALID_STATUS_TRANSITION = 'asset.invalidStatusTransition';

    public const CODE_CUSTOMER_REQUIRED = 'asset.customerRequired';

    public const CODE_CUSTOMER_FORBIDDEN = 'asset.customerForbidden';

    public const CODE_DECOMMISSION_DATE_REQUIRED = 'asset.decommissionDateRequired';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function invalidStatusTransition(string $from, string $to): self {
        return new self(self::CODE_INVALID_STATUS_TRANSITION, "Ungültiger Statuswechsel: {$from} -> {$to}");
    }

    public static function customerRequired(): self {
        return new self(self::CODE_CUSTOMER_REQUIRED, 'customer_id ist erforderlich für owned_by=customer');
    }

    public static function customerForbidden(): self {
        return new self(self::CODE_CUSTOMER_FORBIDDEN, 'customer_id muss leer sein für owned_by=org');
    }

    public static function decommissionDateRequired(): self {
        return new self(self::CODE_DECOMMISSION_DATE_REQUIRED, 'decommissioned_on ist bei Status decommissioned erforderlich');
    }
}
