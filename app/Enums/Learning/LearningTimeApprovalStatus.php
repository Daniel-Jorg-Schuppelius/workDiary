<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTimeApprovalStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Learning;

use App\Enums\Concerns\HasTransitions;
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Freigabestand einer Lernsitzung außerhalb der Arbeitszeit (Feature 149,
 * MVP-749). Leer, solange die Zeitpolitik keine Freigabe verlangt.
 */
enum LearningTimeApprovalStatus: string implements HasStatusTransitions {
    use HasTransitions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /**
     * Entschieden wird genau einmal.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved, self::Rejected => [],
        };
    }
}
