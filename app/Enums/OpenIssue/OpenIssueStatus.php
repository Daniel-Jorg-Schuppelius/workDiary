<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\OpenIssue;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum OpenIssueStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case InProgress = 'inProgress';
    case Blocked = 'blocked';
    case Done = 'done';
    case WontDo = 'wontDo';
    case Reopened = 'reopened';

    public function label(): string {
        return (string) __('enums.open-issue.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Open => 'ghost',
            self::InProgress => 'info',
            self::Blocked => 'warning',
            self::Done => 'success',
            self::WontDo => 'ghost',
            self::Reopened => 'warning',
        };
    }

    public function isOpen(): bool {
        return in_array($this, [self::Open, self::InProgress, self::Blocked, self::Reopened], true);
    }

    public function isClosed(): bool {
        return in_array($this, [self::Done, self::WontDo], true);
    }
}
