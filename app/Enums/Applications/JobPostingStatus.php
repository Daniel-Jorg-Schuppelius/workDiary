<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobPostingStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Stellenveröffentlichung (Feature 068, MVP-189/437): pausiert bleibt sichtbar, ist aber nicht bewerbbar. */
enum JobPostingStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    /** Spaltenvorgabe; der Code legt Veröffentlichungen gleich freigegeben an. */
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';

    /** Setzt der tägliche Lauf `recruiting:expire-postings`, sobald Ablaufdatum oder Bewerbungsschluss vorbei ist. */
    case Expired = 'expired';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /**
     * Pausieren und Ablaufen setzen eine freigegebene Veröffentlichung
     * voraus; die Karriereseite lässt sich aus jedem Stand wieder freigeben.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft, self::Expired => [self::Published, self::Closed],
            self::Published => [self::Paused, self::Expired, self::Closed],
            self::Paused => [self::Published, self::Closed],
            self::Closed => [self::Published],
        };
    }
}
