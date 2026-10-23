<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkItemUrgency.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\WorkQueue;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use Carbon\CarbonImmutable;

/** Dringlichkeit eines Vorgangs, abgeleitet aus seiner Fälligkeit; die Reihenfolge der Fälle ist die Sortierung. */
enum WorkItemUrgency: string implements HasLabel {
    use HasOptions;

    case Overdue = 'overdue';
    case DueSoon = 'due_soon';
    case Open = 'open';

    /** Tage ab heute, in denen ein Vorgang als „bald fällig“ gilt. */
    public const SOON_DAYS = 3;

    public static function for(?CarbonImmutable $dueAt, CarbonImmutable $now): self {
        return match (true) {
            $dueAt === null => self::Open,
            $dueAt->lt($now->startOfDay()) => self::Overdue,
            $dueAt->lte($now->addDays(self::SOON_DAYS)->endOfDay()) => self::DueSoon,
            default => self::Open,
        };
    }

    public function label(): string {
        return (string) __('enums.work_queue.urgency.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Overdue => 'error',
            self::DueSoon => 'warning',
            self::Open => 'ghost',
        };
    }

    public function rank(): int {
        return match ($this) {
            self::Overdue => 0,
            self::DueSoon => 1,
            self::Open => 2,
        };
    }
}
