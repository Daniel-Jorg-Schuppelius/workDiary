<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingScanPending.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Whistleblowing\Attachment;
use App\Scheduling\Contracts\JobGuard;

/** Hinweisgeber-Anhänge (whistleblowing:scan): nur mit Anhang in der Prüfwarteschlange. */
final class WhistleblowingScanPending implements JobGuard {
    public function shouldRun(): bool {
        return Attachment::withoutGlobalScopes()->where('scan_status', AttachmentScanStatus::Pending->value)->exists();
    }
}
