<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceEndKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art des Rückgabe-/Ende-Prozesses (MVP-276): jede Entscheidung ist ein
 * nachvollziehbarer Vorgang mit Protokoll und Kostenfolge.
 */
enum AssetFinanceEndKind: string implements HasLabel {
    use HasOptions;

    case Return = 'return';
    case Purchase = 'purchase';
    case Extension = 'extension';
    case Replacement = 'replacement';

    public function label(): string {
        return match ($this) {
            self::Return => (string) __('Rückgabe'),
            self::Purchase => (string) __('Kauf/Übernahme'),
            self::Extension => (string) __('Verlängerung'),
            self::Replacement => (string) __('Ersatzinvestition'),
        };
    }

    public function resultingStatus(): AssetFinanceStatus {
        return match ($this) {
            self::Return => AssetFinanceStatus::Returned,
            self::Purchase => AssetFinanceStatus::Purchased,
            self::Extension => AssetFinanceStatus::Extended,
            self::Replacement => AssetFinanceStatus::Returned,
        };
    }
}
