<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Customer;

use App\Enums\Attachments\UploadPurpose;
use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Leistungsart eines Kundeneingangs (MVP-1074): je Art eine feste Vorlage und ein Übernahmeziel. */
enum IntakeKind: string implements HasLabel {
    use HasOptions;

    case Print = 'print';
    case It = 'it';

    public function label(): string {
        return (string) __('customer_intake.kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Print => 'print',
            self::It => 'computer',
        };
    }

    /** Druckdaten brauchen Großformate (EPS, AI, TIFF), IT-Anhänge die allgemeine Liste. */
    public function uploadPurpose(): UploadPurpose {
        return match ($this) {
            self::Print => UploadPurpose::PrintData,
            self::It => UploadPurpose::General,
        };
    }
}
