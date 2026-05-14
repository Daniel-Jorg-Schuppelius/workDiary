<?php

namespace App\Observers;

use App\Models\Attachment;
use App\Models\DiaryEntry;
use App\Services\PushNotifier;

class AttachmentObserver
{
    public function created(Attachment $attachment): void
    {
        if ($attachment->attachable_type === DiaryEntry::class) {
            $attachment->loadMissing('attachable.user', 'uploader');
        }

        app(PushNotifier::class)->newAttachment($attachment);
    }
}
