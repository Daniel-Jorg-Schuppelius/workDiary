<?php

namespace App\Mail;

use App\Models\DiaryEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DiaryStatusChangedMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public DiaryEntry $entry, public ?int $oldStatus, public int $newStatus) {
    }

    public function envelope(): Envelope {
        return new Envelope(subject: __('Status geändert: Tagebuch-Eintrag #:id', ['id' => $this->entry->id]));
    }

    public function content(): Content {
        return new Content(view: 'mail.diary-status-changed', with: [
            'entry' => $this->entry,
            'oldStatus' => $this->oldStatus,
            'newStatus' => $this->newStatus,
        ]);
    }
}
