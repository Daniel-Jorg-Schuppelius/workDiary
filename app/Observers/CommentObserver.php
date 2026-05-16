<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\Comment;
use App\Services\MailNotifier;
use App\Services\PushNotifier;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        $comment->loadMissing('diaryEntry.user', 'user');

        app(PushNotifier::class)->newComment($comment);
        app(MailNotifier::class)->commentCreated($comment);
    }
}
