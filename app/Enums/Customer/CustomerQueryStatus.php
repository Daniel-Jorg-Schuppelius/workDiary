<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerQueryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Customer;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status einer Kunden-Rückfrage (Feature 012, Kundenportal & Freigaben).
 */
enum CustomerQueryStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.customer-query.status.' . $this->value);
    }
}
