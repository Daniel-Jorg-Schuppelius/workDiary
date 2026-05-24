<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\{Attachment, DiaryEntry};
use App\Services\PushNotifier;

class AttachmentObserver {
    public function created(Attachment $attachment): void {
        if ($attachment->attachable_type === DiaryEntry::class) {
            $attachment->loadMissing('attachable.user', 'uploader');
        }

        app(PushNotifier::class)->newAttachment($attachment);
    }
}
