<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareInstallationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

class SoftwareInstallationException extends RuntimeException {
    public const CODE_OS_ALREADY_EXISTS = 'softwareInstallation.osAlreadyExists';

    public const CODE_ORG_MISMATCH = 'softwareInstallation.organizationMismatch';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function osAlreadyExists(): self {
        return new self(self::CODE_OS_ALREADY_EXISTS, 'Für dieses Asset ist bereits ein Betriebssystem hinterlegt.');
    }

    public static function organizationMismatch(): self {
        return new self(self::CODE_ORG_MISMATCH, 'Asset und Software gehören nicht zur selben Organisation.');
    }
}
