<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand eines Sicherheitseinbehalts (Feature 113, MVP-602).
 *
 * `Secured` bedeutet: durch eine Bürgschaft abgelöst und ausgezahlt
 * (MVP-603). Fachlich ist das etwas anderes als `Released` — dort endete die
 * Sicherungsfrist, hier wurde die Sicherheit ersetzt.
 */
enum RetentionStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Released = 'released';
    case Secured = 'secured';

    public function label(): string {
        return (string) __('enums.retention_status.' . $this->value);
    }

    /** Mindert dieser Einbehalt aktuell den fälligen Betrag? */
    public function reducesOpenAmount(): bool {
        return $this === self::Open;
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Released => 'success',
            self::Secured => 'info',
        };
    }
}
