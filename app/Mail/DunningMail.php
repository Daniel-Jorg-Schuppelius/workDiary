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
use App\Services\Invoicing\{DunningPdfRenderer, InvoicePdfRenderer};
use App\Support\DocumentNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Zahlungserinnerung/Mahnung zu einer überfälligen Rechnung (Feature 066,
 * MVP-163; Ausbau MVP-650): Stufe 1 = Zahlungserinnerung, Stufen 2–3 =
 * Mahnung. Angehängt sind das eigentliche Mahnschreiben als PDF
 * ({@see DunningPdfRenderer}, inkl. optionaler Mahngebühr und Zahlungsziel)
 * sowie die Original-Rechnung — es entsteht weiterhin KEIN neuer Beleg.
 */
class DunningMail extends Mailable implements ShouldQueue {
    use Queueable, SerializesModels;

    /** @param array{rate: float, days: int, amount: float}|null $interest Verzugszins-Ausweis (MVP-691) */
    public function __construct(
        public Invoice $invoice,
        public int $level,
        public ?string $note = null,
        public ?int $dispatchId = null,
        public ?float $fee = null,
        public ?\Carbon\CarbonImmutable $payUntil = null,
        public ?array $interest = null,
    ) {
        // Belegsprache je Kunde (Feature 034, MVP-721): Betreff, Text und
        // Mahnschreiben entstehen in Mailable::send() innerhalb dieser Locale.
        $this->locale(\App\Support\DocumentLocale::for($invoice->customer, $invoice->organization));
    }

    public function envelope(): Envelope {
        return new Envelope(subject: $this->subjectLine());
    }

    public function headers(): \Illuminate\Mail\Mailables\Headers {
        // Vollaudit 2026-07 (M26): Dispatch-Referenz für den Zustellnachweis.
        return new \Illuminate\Mail\Mailables\Headers(text: array_filter([
            \App\Listeners\RecordInvoiceMailDelivery::HEADER => $this->dispatchId !== null ? (string) $this->dispatchId : null,
        ]));
    }

    /** Queue-Fehlschlag → Zustellnachweis auf failed (Vollaudit 2026-07, M26). */
    public function failed(\Throwable $exception): void {
        if ($this->dispatchId === null) {
            return;
        }
        $dispatch = \App\Models\DocumentDispatch::query()->withoutGlobalScopes()->find($this->dispatchId);
        $dispatch?->forceFill([
            'status' => 'failed',
            'meta' => [...(array) $dispatch->meta, 'error' => mb_substr($exception->getMessage(), 0, 500)],
        ])->save();
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
                    'total' => DocumentNumber::decimal(($this->invoice->total?->toFloat() ?? 0.0), 2),
                    'currency' => $this->invoice->currency->value,
                ])
                : (string) __('trotz vorheriger Erinnerung ist die Rechnung :number vom :date über :total :currency weiterhin offen (Mahnstufe :level). Bitte begleichen Sie den Betrag umgehend.', [
                    'number' => $this->invoice->number,
                    'date' => optional($this->invoice->issued_on)->isoFormat('L') ?? '—',
                    'total' => DocumentNumber::decimal(($this->invoice->total?->toFloat() ?? 0.0), 2),
                    'currency' => $this->invoice->currency->value,
                    'level' => $this->level,
                ]),
            '',
            (string) __('Fällig war die Rechnung am :date.', ['date' => optional($this->invoice->due_on)->isoFormat('L') ?? '—']),
        ];
        // Verzugszins-Ausweis (MVP-691): nur Text — gebucht wird nichts.
        if ($this->interest !== null) {
            $lines[] = '';
            $lines[] = (string) __('finance.dunning.mail_interest', [
                'amount' => DocumentNumber::decimal($this->interest['amount'], 2),
                'currency' => $this->invoice->currency->value,
                'rate' => DocumentNumber::decimal($this->interest['rate'], 2),
                'days' => $this->interest['days'],
            ]);
        }
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
        // MVP-650: das Mahnschreiben ist der Hauptanhang, die Original-Rechnung
        // liegt als Nachweis bei.
        $letter = app(DunningPdfRenderer::class)->output($this->invoice, $this->level, $this->note, $this->fee, $this->payUntil, $this->interest);
        $invoicePdf = app(InvoicePdfRenderer::class)->output($this->invoice);
        $letterName = $this->level <= 1
            ? sprintf('zahlungserinnerung-%s.pdf', $this->invoice->number)
            : sprintf('mahnung-%s-stufe%d.pdf', $this->invoice->number, $this->level);

        return [
            Attachment::fromData(static fn(): string => $letter, $letterName)->withMime('application/pdf'),
            Attachment::fromData(static fn(): string => $invoicePdf, sprintf('rechnung-%s.pdf', $this->invoice->number))
                ->withMime('application/pdf'),
        ];
    }
}
