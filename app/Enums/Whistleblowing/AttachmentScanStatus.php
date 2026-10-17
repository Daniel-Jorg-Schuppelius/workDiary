<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentScanStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Whistleblowing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum AttachmentScanStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Clean = 'clean';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('whistleblowing.attachment_scan.' . $this->value);
    }
}
