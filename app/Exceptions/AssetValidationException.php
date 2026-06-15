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

    public const CODE_ROOM_CUSTOMER_MISMATCH = 'asset.roomCustomerMismatch';

    public const CODE_NOT_AVAILABLE = 'asset.notAvailableForCheckout';

    public const CODE_ALREADY_CHECKED_OUT = 'asset.alreadyCheckedOut';

    public const CODE_BLOCKED_BY_DEFECT = 'asset.blockedByDefect';

    public const CODE_ASSIGNMENT_TARGET_REQUIRED = 'asset.assignmentTargetRequired';

    public const CODE_ASSIGNMENT_ALREADY_RETURNED = 'asset.assignmentAlreadyReturned';

    public const CODE_DEFECT_RESOLUTION_NOTE_REQUIRED = 'asset.defectResolutionNoteRequired';

    public const CODE_INVALID_DEFECT_TRANSITION = 'asset.invalidDefectTransition';

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

    public static function roomCustomerMismatch(): self {
        return new self(self::CODE_ROOM_CUSTOMER_MISMATCH, 'Der gewählte Raum gehört nicht zum gewählten Kunden.');
    }

    public static function notAvailableForCheckout(): self {
        return new self(self::CODE_NOT_AVAILABLE, 'Das Asset ist nicht verfügbar (außer Betrieb, ersetzt oder verloren).');
    }

    public static function alreadyCheckedOut(): self {
        return new self(self::CODE_ALREADY_CHECKED_OUT, 'Das Asset ist bereits ausgegeben.');
    }

    public static function blockedByDefect(): self {
        return new self(self::CODE_BLOCKED_BY_DEFECT, 'Das Asset ist wegen eines Defekts gesperrt und kann nicht ausgegeben werden.');
    }

    public static function assignmentTargetRequired(): self {
        return new self(self::CODE_ASSIGNMENT_TARGET_REQUIRED, 'Es muss eine Person oder ein Team als Empfänger angegeben werden.');
    }

    public static function assignmentAlreadyReturned(): self {
        return new self(self::CODE_ASSIGNMENT_ALREADY_RETURNED, 'Diese Zuweisung wurde bereits zurückgegeben.');
    }

    public static function defectResolutionNoteRequired(): self {
        return new self(self::CODE_DEFECT_RESOLUTION_NOTE_REQUIRED, 'Für das Erledigen/Ausbuchen eines Defekts ist eine Notiz erforderlich.');
    }

    public static function invalidDefectTransition(string $from, string $to): self {
        return new self(self::CODE_INVALID_DEFECT_TRANSITION, "Ungültiger Defekt-Statuswechsel: {$from} -> {$to}");
    }
}
