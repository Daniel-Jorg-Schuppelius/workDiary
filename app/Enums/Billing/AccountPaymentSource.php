<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountPaymentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Herkunft einer Zahlung auf dem Kundenkonto (Feature 098).
 */
enum AccountPaymentSource: string implements HasLabel {
    use HasOptions;

    case Manual = 'manual';
    case Bank = 'bank';
    case Import = 'import';
    case Lexoffice = 'lexoffice';

    public function label(): string {
        return (string) __('enums.billing.account-payment-source.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Manual => 'ghost',
            self::Bank => 'info',
            self::Import => 'warning',
            self::Lexoffice => 'success',
        };
    }
}
