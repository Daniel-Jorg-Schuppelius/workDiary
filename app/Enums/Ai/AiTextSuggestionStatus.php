<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiTextSuggestionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines KI-Vorschlags (Feature 084): offen, bis er übernommen, verworfen oder verfallen ist. */
enum AiTextSuggestionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Proposed = 'proposed';
    case Accepted = 'accepted';

    /** Mit eigenen Änderungen übernommen. */
    case Edited = 'edited';
    case Rejected = 'rejected';

    /** Nicht entschieden, bevor der Beleg ausgestellt wurde oder die Frist ablief. */
    case Expired = 'expired';

    public function label(): string {
        return match ($this) {
            self::Proposed => (string) __('Vorgeschlagen'),
            self::Accepted => (string) __('Angenommen'),
            self::Edited => (string) __('Geändert'),
            self::Rejected => (string) __('Abgelehnt'),
            self::Expired => (string) __('Abgelaufen'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Proposed => [self::Accepted, self::Edited, self::Rejected, self::Expired],
            self::Accepted, self::Edited, self::Rejected, self::Expired => [],
        };
    }
}
