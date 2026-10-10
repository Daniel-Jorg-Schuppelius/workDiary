<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNoticeMatchState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Tenders;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Zustand eines Radar-Treffers (MVP-630): ungesehen, verworfen oder in einen Vergabevorgang übernommen. */
enum TenderNoticeMatchState: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case New = 'new';
    case Muted = 'muted';
    case Converted = 'converted';

    public function label(): string {
        return match ($this) {
            self::New => (string) __('enums.tenders.tender_notice_match_state.new'),
            self::Muted => (string) __('enums.tenders.tender_notice_match_state.muted'),
            self::Converted => (string) __('enums.tenders.tender_notice_match_state.converted'),
        };
    }

    /**
     * Auch ein ausgeblendeter Treffer lässt sich noch übernehmen; die
     * Übernahme ist endgültig.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::New => [self::Muted, self::Converted],
            self::Muted => [self::New, self::Converted],
            self::Converted => [],
        };
    }
}
