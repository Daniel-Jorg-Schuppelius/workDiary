<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Customer;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status eines Kundeneingangs vor der Übernahme (MVP-1074/1075). Angebot und
 * Entscheidung leitet der Eingang aus dem verknüpften Angebot ab; nach der
 * Übernahme ist die Fachakte maßgeblich — keine zweite Auftragsstatusmaschine.
 */
enum IntakeStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case AwaitingCustomer = 'awaiting_customer';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case HandedOver = 'handed_over';

    /** @return list<self> Noch in Bearbeitung vor der Übernahme. */
    public static function open(): array {
        return [self::Submitted, self::InProgress, self::AwaitingCustomer];
    }

    public function isOpen(): bool {
        return in_array($this, self::open(), true);
    }

    public function label(): string {
        return (string) __('customer_intake.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Submitted => 'info',
            self::InProgress => 'primary',
            self::AwaitingCustomer => 'warning',
            self::Rejected => 'error',
            self::Withdrawn => 'neutral',
            self::HandedOver => 'success',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Submitted => [self::InProgress, self::AwaitingCustomer, self::Rejected, self::Withdrawn],
            self::InProgress => [self::AwaitingCustomer, self::Rejected, self::Withdrawn, self::HandedOver],
            self::AwaitingCustomer => [self::InProgress, self::Rejected, self::Withdrawn, self::HandedOver],
            self::Rejected, self::Withdrawn, self::HandedOver => [],
        };
    }
}
