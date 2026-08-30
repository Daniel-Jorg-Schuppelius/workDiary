<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParticipantStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Event;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ParticipantStatus: string implements HasLabel {
    use HasOptions;

    case Invited = 'invited';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Attended = 'attended';
    case NoShow = 'no_show';
    // Warteliste (Feature 149, MVP-741): der Termin ist voll, die Person
    // rückt automatisch nach, sobald ein Platz frei wird.
    case Waitlisted = 'waitlisted';

    public function label(): string {
        return (string) __('enums.event.participant.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Invited => 'ghost',
            self::Accepted => 'info',
            self::Declined => 'error',
            self::Attended => 'success',
            self::NoShow => 'warning',
            self::Waitlisted => 'neutral',
        };
    }
}
