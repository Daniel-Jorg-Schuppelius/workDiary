<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\OpenIssue;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum OpenIssueVisibility: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case Customer = 'customer';

    public function label(): string {
        return (string) __('enums.open-issue.visibility.' . $this->value);
    }
}
