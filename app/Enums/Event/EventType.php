<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Event;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum EventType: string implements HasLabel {
    use HasOptions;

    case Training = 'training';
    case Workshop = 'workshop';
    case Conference = 'conference';
    case Meeting = 'meeting';
    case InternalBriefing = 'internal_briefing';
    case ExternalVisit = 'external_visit';

    public function label(): string {
        return (string) __('enums.event.type.' . $this->value);
    }
}
