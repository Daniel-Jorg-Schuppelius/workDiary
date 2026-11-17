<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Wishes;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art eines Wunsches an die App (Feature 206, MVP-1658). */
enum AppWishKind: string implements HasLabel {
    use HasOptions;

    case Wish = 'wish';
    case Bug = 'bug';
    case Question = 'question';

    public function label(): string {
        return (string) __('wishes.kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Wish => 'lightbulb',
            self::Bug => 'bug_report',
            self::Question => 'help',
        };
    }
}
