<?php

namespace App\Observers;

use App\Models\DiaryEntry;
use App\Services\MailNotifier;
use App\Services\PushNotifier;

class DiaryEntryObserver
{
    public function created(DiaryEntry $entry): void
    {
        app(PushNotifier::class)->diaryProblem($entry);
    }

    public function updated(DiaryEntry $entry): void
    {
        if (! $entry->wasChanged('status')) {
            return;
        }

        app(PushNotifier::class)->diaryProblem($entry);
        app(MailNotifier::class)->diaryStatusChanged(
            $entry,
            $entry->getOriginal('status') !== null ? (int) $entry->getOriginal('status') : null,
            (int) $entry->status,
        );
    }
}
