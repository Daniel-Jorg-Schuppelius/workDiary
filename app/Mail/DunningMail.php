<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Zahlungserinnerung/Mahnung zu einer überfälligen Rechnung (Feature 066,
 * MVP-163): Stufe 1 = Zahlungserinnerung, Stufen 2–3 = Mahnung. Die
 * Original-Rechnung hängt als PDF an — es entsteht KEIN neuer Beleg.
 */
class DunningMail extends Mailable implements ShouldQueue {
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public int $level,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: $this->subjectLine());
    }

    public function subjectLine(): string {
        return $this->level <= 1
            ? (string) __('Zahlungserinnerung zur Rechnung :number', ['number' => $this->invoice->number])
            : (string) __(':level. Mahnung zur Rechnung :number', ['level' => $this->level, 'number' => $this->invoice->number]);
    }

    public function content(): Content {
        $this->invoice->loadMissing('customer');
        $lines = [
            (string) __('Sehr geehrte Damen und Herren,'),
            '',
            $this->level <= 1
                ? (string) __('zur Rechnung :number vom :date über :total :currency konnten wir bislang keinen Zahlungseingang feststellen. Sicher handelt es sich um ein Versehen — bitte gleichen Sie den offenen Betrag aus.', [
                    'number' => $this->invoice->number,
                    'date' => optional($this->invoice->issued_on)->isoFormat('L') ?? '—',
                    'total' => number_format((float) $this->invoice->total, 2, ',', '.'),
                    'currency' => $this->invoice->currency->value,
                ])
                : (string) __('trotz vorheriger Erinnerung ist die Rechnung :number vom :date über :total :currency weiterhin offen (Mahnstufe :level). Bitte begleichen Sie den Betrag umgehend.', [
                    'number' => $this->invoice->number,
                    'date' => optional($this->invoice->issued_on)->isoFormat('L') ?? '—',
                    'total' => number_format((float) $this->invoice->total, 2, ',', '.'),
                    'currency' => $this->invoice->currency->value,
                    'level' => $this->level,
                ]),
            '',
            (string) __('Fällig war die Rechnung am :date.', ['date' => optional($this->invoice->due_on)->isoFormat('L') ?? '—']),
        ];
        if ($this->note !== null && trim($this->note) !== '') {
            $lines[] = '';
            $lines[] = trim($this->note);
        }
        $lines[] = '';
        $lines[] = (string) __('Sollte sich diese Nachricht mit Ihrer Zahlung überschnitten haben, betrachten Sie sie bitte als gegenstandslos.');
        $text = implode("\n", $lines);

        return new Content(
            view: 'mail.invoice',
            text: 'mail.invoice-text',
            with: [
                'invoice' => $this->invoice,
                'html' => nl2br(e($text)),
                'text' => $text,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array {
        $this->invoice->loadMissing(['items', 'customer', 'project', 'parent']);
        $bytes = app(InvoicePdfRenderer::class)->output($this->invoice);

        return [
            Attachment::fromData(static fn(): string => $bytes, sprintf('rechnung-%s.pdf', $this->invoice->number))
                ->withMime('application/pdf'),
        ];
    }
}
