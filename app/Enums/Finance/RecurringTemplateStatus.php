<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringTemplateStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer wiederkehrenden Vorlage (Feature 125, MVP-675).
 */
enum RecurringTemplateStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string {
        return (string) __('enums.finance.recurring-template-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Ended => 'ghost',
        };
    }

    public function runs(): bool {
        return $this === self::Active;
    }
}
