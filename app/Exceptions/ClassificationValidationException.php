<?php

/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationValidationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

/**
 * Validierungs- und Schutz-Fehler bei der Klassifikationspflege (MVP-031).
 */
class ClassificationValidationException extends RuntimeException {
    public const CODE_INVALID_CODE = 'classification.invalidCode';

    public const CODE_INVALID_LABEL = 'classification.invalidLabel';

    public const CODE_INVALID_COLOR = 'classification.invalidColor';

    public const CODE_DUPLICATE = 'classification.duplicate';

    public const CODE_REFERENCED = 'classification.referencedByEntities';

    public const CODE_PLATFORM_PROTECTED = 'classification.platformDefaultProtected';

    public const CODE_IMPORT_TOO_LARGE = 'classification.importTooLarge';

    public const CODE_IMPORT_INVALID = 'classification.importInvalid';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function invalidCode(string $code): self {
        return new self(self::CODE_INVALID_CODE, "Ungültiger Code: {$code}", $code);
    }

    public static function invalidLabel(): self {
        return new self(self::CODE_INVALID_LABEL, 'Label ist erforderlich (1..180 Zeichen).');
    }

    public static function invalidColor(string $color): self {
        return new self(self::CODE_INVALID_COLOR, "Farbe muss #RRGGBB sein: {$color}", $color);
    }

    public static function duplicate(string $code): self {
        return new self(self::CODE_DUPLICATE, "Code bereits vorhanden: {$code}", $code);
    }

    public static function referenced(): self {
        return new self(self::CODE_REFERENCED, 'Klassifikation wird noch referenziert und kann nicht gelöscht werden.');
    }

    public static function platformProtected(): self {
        return new self(self::CODE_PLATFORM_PROTECTED, 'Plattform-Defaults können nur über Org-Override deaktiviert werden.');
    }

    public static function importTooLarge(int $rows, int $max): self {
        return new self(self::CODE_IMPORT_TOO_LARGE, "Import zu groß: {$rows} (max {$max}).");
    }

    public static function importInvalid(int $line, string $reason): self {
        return new self(self::CODE_IMPORT_INVALID, "Zeile {$line}: {$reason}", $reason);
    }
}
