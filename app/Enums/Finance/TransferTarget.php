<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ziel eines Übergabenachweises (Feature 045): API-Übergabe an Lexoffice
 * oder DATEV bzw. dateibasierte Übergabe (Export-Paket).
 */
enum TransferTarget: string implements HasLabel {
    use HasOptions;

    case Lexoffice = 'lexoffice';
    case Datev = 'datev';
    case OrgaMax = 'orgamax';
    case SevDesk = 'sevdesk';
    case File = 'file';

    public function label(): string {
        return (string) __('enums.finance.transfer-target.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Lexoffice => 'info',
            self::Datev => 'warning',
            self::OrgaMax => 'success',
            self::SevDesk => 'secondary',
            self::File => 'ghost',
        };
    }
}
