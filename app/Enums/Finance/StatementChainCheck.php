<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementChainCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Saldenkette zwischen aufeinanderfolgenden Auszügen eines Kontos (MVP-1279):
 *   ok       = Eröffnungssaldo == Schlusssaldo des Vorgängers (±1 ct), lückenlos
 *   gap      = zwischen Vorgänger und Auszug fehlen Tage (fehlender Auszug)
 *   mismatch = Salden schließen nicht aneinander an
 *   first    = erster Auszug des Kontos, nichts zu vergleichen
 */
enum StatementChainCheck: string implements HasLabel {
    use HasOptions;

    case Ok = 'ok';
    case Gap = 'gap';
    case Mismatch = 'mismatch';
    case First = 'first';

    public function label(): string {
        return (string) __('enums.finance.statement-chain-check.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Ok => 'success',
            self::Gap => 'warning',
            self::Mismatch => 'error',
            self::First => 'neutral',
        };
    }
}
