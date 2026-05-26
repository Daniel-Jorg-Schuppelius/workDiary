<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportRunState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Import;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus-Zustand eines CSV-Import-Laufs (MVP-049).
 */
enum ImportRunState: string implements HasLabel {
    use HasOptions;

    case Preflight = 'preflight';
    case AwaitingApproval = 'awaitingApproval';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('import.state.' . $this->value);
    }

    public function isFinal(): bool {
        return match ($this) {
            self::Succeeded, self::Partial, self::Failed => true,
            default => false,
        };
    }

    public function canConfirm(): bool {
        return $this === self::AwaitingApproval;
    }
}
