<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrderStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Print;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Druckauftrags-Status (MVP-459): Datenprüfung → Freigabe → Produktion →
 * Qualitätskontrolle → bereit → ausgegeben. Eine Dateiänderung nach Freigabe
 * setzt den Auftrag über den Service zurück auf Datenprüfung — nie still.
 */
enum PrintOrderStatus: string implements HasLabel {
    use HasOptions;

    case DataCheck = 'data_check';
    case Approved = 'approved';
    case InProduction = 'in_production';
    case QualityCheck = 'quality_check';
    case Rework = 'rework';
    case Ready = 'ready';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.print.order_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Issued => 'success',
            self::Ready => 'success',
            self::Cancelled => 'error',
            self::Rework => 'warning',
            self::InProduction, self::QualityCheck => 'info',
            default => 'neutral',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::DataCheck => [self::Approved, self::Cancelled],
            self::Approved => [self::InProduction, self::DataCheck, self::Cancelled],
            self::InProduction => [self::QualityCheck, self::Cancelled],
            self::QualityCheck => [self::Ready, self::Rework],
            self::Rework => [self::InProduction, self::DataCheck, self::Cancelled],
            self::Ready => [self::Issued],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Endzustand — Snapshots und Nachweise sind eingefroren. */
    public function isFinal(): bool {
        return $this->allowedTransitions() === [];
    }
}
