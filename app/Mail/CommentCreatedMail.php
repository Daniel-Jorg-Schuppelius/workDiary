<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentCreatedMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class CommentCreatedMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function envelope(): Envelope {
        $entryId = $this->comment->commentable_id;

        return new Envelope(subject: __('Neuer Kommentar zum Tagebuch-Eintrag #:id', ['id' => $entryId]));
    }

    public function content(): Content {
        return new Content(view: 'mail.comment-created', with: [
            'comment' => $this->comment,
        ]);
    }
}
