<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Status.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Diary;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum Status: int implements HasLabel {
    use HasOptions;

    case Done = -1;
    case InProgress = 1;
    case Open = 2;
    case Problem = 3;
    case Accepted = 4;
    case WaitingMaterial = 5;
    case AcceptedFinal = 6;
    case Invoiced = 7;
    case Cancelled = 8;

    public const Completed = self::Done;
    public const Planned = self::Open;
    public const WaitingCustomer = self::Problem;

    public function label(): string {
        return (string) __('diary.status.' . $this->key());
    }

    public function tone(): string {
        return match ($this) {
            self::Done => 'done',
            self::InProgress, self::Accepted => 'progress',
            self::Open => 'open',
            self::Problem, self::WaitingMaterial => 'alert',
            self::AcceptedFinal, self::Invoiced => 'done',
            self::Cancelled => 'neutral',
        };
    }

    public function key(): string {
        return match ($this) {
            self::Done => 'Completed',
            self::Open => 'Planned',
            self::Problem => 'WaitingCustomer',
            default => $this->name,
        };
    }

    public function slug(): string {
        return match ($this) {
            self::Done => 'completed',
            self::InProgress => 'in_progress',
            self::Open => 'planned',
            self::Problem => 'waiting_customer',
            self::Accepted => 'accepted',
            self::WaitingMaterial => 'waiting_material',
            self::AcceptedFinal => 'accepted_final',
            self::Invoiced => 'invoiced',
            self::Cancelled => 'cancelled',
        };
    }

    /** @return list<string> */
    public function allowedActions(): array {
        return match ($this) {
            self::Open => ['accept', 'cancel'],
            self::Accepted => ['start', 'cancel'],
            self::InProgress => ['pause', 'complete', 'cancel'],
            self::Problem, self::WaitingMaterial => ['resume'],
            self::Done => ['handover'],
            self::AcceptedFinal => ['markInvoiced'],
            self::Invoiced, self::Cancelled => [],
        };
    }
}
