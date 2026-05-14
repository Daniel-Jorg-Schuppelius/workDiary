<?php

namespace App\Mail;

use App\Models\Timesheet;
use App\Services\Timesheet\PdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TimesheetSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Timesheet $timesheet) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Stundenzettel signiert: :date', [
            'date' => $this->timesheet->work_date->format('d.m.Y'),
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.timesheet-signed', with: [
            'timesheet' => $this->timesheet,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $bytes = app(PdfRenderer::class)->render($this->timesheet);

        return [
            Attachment::fromData(fn (): string => $bytes, sprintf('stundenzettel-%d.pdf', $this->timesheet->id))
                ->withMime('application/pdf'),
        ];
    }
}
