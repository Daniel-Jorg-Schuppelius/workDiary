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

enum AttachmentScanStatus: string {
    case Pending = 'pending';
    case Clean = 'clean';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
