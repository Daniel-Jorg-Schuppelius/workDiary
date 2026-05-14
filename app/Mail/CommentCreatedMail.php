<?php

namespace App\Mail;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommentCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function envelope(): Envelope
    {
        $entryId = $this->comment->diary_entry_id;

        return new Envelope(subject: __('Neuer Kommentar zum Tagebuch-Eintrag #:id', ['id' => $entryId]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.comment-created', with: [
            'comment' => $this->comment,
        ]);
    }
}
