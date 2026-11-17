<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Wishes;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Stand eines Wunsches an die App (Feature 206, MVP-1658): neu → in Prüfung
 * → geplant → umgesetzt, jederzeit abgelehnt. Dieselben Stände nutzt der
 * Plattformbetrieb für seine eigene Einschätzung (MVP-1659,
 * `app_wishes.platform_status`). Eine Ablehnung lässt sich wieder in Prüfung
 * nehmen; ein umgesetzter Wunsch ist abgeschlossen.
 */
enum AppWishStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case New = 'new';
    case InReview = 'in_review';
    case Planned = 'planned';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('wishes.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::New => 'info',
            self::InReview => 'warning',
            self::Planned => 'primary',
            self::Done => 'success',
            self::Rejected => 'ghost',
        };
    }

    public function isOpen(): bool {
        return in_array($this, [self::New, self::InReview, self::Planned], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::New => [self::InReview, self::Planned, self::Done, self::Rejected],
            self::InReview => [self::Planned, self::Done, self::Rejected],
            self::Planned => [self::InReview, self::Done, self::Rejected],
            self::Rejected => [self::InReview],
            self::Done => [],
        };
    }
}
