<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer erwarteten Abrechnungsperiode. Entschiedene Zustände
 * (berechnet, teilweise, verzichtet, strittig) überlebt jede Neuplanung.
 */
enum PeriodStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Billed = 'billed';
    case Partial = 'partial';
    case Waived = 'waived';
    case Disputed = 'disputed';

    public function label(): string {
        return (string) __('resale.period_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'error',
            self::Billed => 'success',
            self::Partial => 'warning',
            self::Waived => 'neutral',
            self::Disputed => 'warning',
        };
    }

    /** Vom Nutzer oder einer Zuordnung entschieden — die Planung fasst sie nicht mehr an. */
    public function isDecided(): bool {
        return $this !== self::Open;
    }
}
