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

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Zustand eines Sicherheitseinbehalts (Feature 113, MVP-602).
 *
 * `Secured` bedeutet: durch eine Bürgschaft abgelöst und ausgezahlt
 * (MVP-603). Fachlich ist das etwas anderes als `Released` — dort endete die
 * Sicherungsfrist, hier wurde die Sicherheit ersetzt. Endet der
 * Sicherungszweck eines abgelösten Einbehalts, wird auch er `Released`;
 * erst dann ist die Urkunde zurückzugeben (MVP-1561).
 */
enum RetentionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Released = 'released';
    case Secured = 'secured';

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Released, self::Secured],
            self::Secured => [self::Released],
            self::Released => [],
        };
    }

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
