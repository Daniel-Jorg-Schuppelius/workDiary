<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionProposalStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Zwei Stufen bis zur Löschung: Vorschlag bestätigen, dann löschen. */
enum RetentionProposalStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Purged = 'purged';

    public function label(): string {
        return match ($this) {
            self::Pending => __('offen'),
            self::Approved => __('bestätigt'),
            self::Rejected => __('abgelehnt'),
            self::Purged => __('gelöscht'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected, self::Purged => 'ghost',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Purged],
            self::Rejected, self::Purged => [],
        };
    }
}
