<?php

namespace App\Observers;

use App\Models\Comment;
use App\Services\MailNotifier;
use App\Services\PushNotifier;

class CommentObserver {
    public function created(Comment $comment): void {
        $comment->loadMissing('diaryEntry.user', 'user');

        app(PushNotifier::class)->newComment($comment);
        app(MailNotifier::class)->commentCreated($comment);
    }
}
