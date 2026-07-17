<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileItemType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Agile;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Typ eines Arbeitselements im Produkt-Backlog (Feature 064). */
enum AgileItemType: string implements HasLabel {
    use HasOptions;

    case Epic = 'epic';
    case Story = 'story';
    case Task = 'task';
    case Bug = 'bug';

    public function label(): string {
        return match ($this) {
            self::Epic => (string) __('Epic'),
            self::Story => (string) __('Story'),
            self::Task => (string) __('Aufgabe'),
            self::Bug => (string) __('Fehler'),
        };
    }
}
