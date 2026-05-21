<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParticipantRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Event;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ParticipantRole: string implements HasLabel {
    use HasOptions;

    case Organizer = 'organizer';
    case Trainer = 'trainer';
    case Attendee = 'attendee';
    case Optional = 'optional';

    public function label(): string {
        return (string) __('enums.event.participant.role.' . $this->value);
    }
}
