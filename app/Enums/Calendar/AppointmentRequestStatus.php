<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Calendar;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Terminwunschs (Feature 095): angefragt, intern entschieden, danach nur noch Storno oder Umbuchung. */
enum AppointmentRequestStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Requested = 'requested';
    /** Die Disposition schlägt einen anderen Termin vor (MVP-1287). */
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Canceled = 'canceled';

    /** Durch eine Umbuchung abgelöst (nur Calendly). */
    case Superseded = 'superseded';

    public function label(): string {
        return (string) __('appointment.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requested => 'info',
            self::Proposed => 'warning',
            self::Confirmed => 'success',
            self::Declined => 'error',
            self::Canceled, self::Superseded => 'ghost',
        };
    }

    /**
     * Eine intern abgelehnte Calendly-Buchung besteht dort weiter; der Gast
     * kann sie noch absagen oder umbuchen.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Proposed, self::Confirmed, self::Declined, self::Canceled, self::Superseded],
            self::Proposed => [self::Confirmed, self::Declined, self::Canceled],
            self::Confirmed, self::Declined => [self::Canceled, self::Superseded],
            self::Canceled, self::Superseded => [],
        };
    }
}
