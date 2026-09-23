<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeNoticeMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Club\ClubFeeClaim;
use App\Models\Document\DocumentDispatch;
use App\Services\Club\ClubFeeNoticePdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\{Attachment, Mailable};
use Illuminate\Mail\Mailables\{Content, Envelope, Headers};
use Illuminate\Queue\SerializesModels;

/**
 * Beitragsmitteilung per E-Mail (MVP-850): PDF-Anhang aus dem Renderer,
 * Zustellnachweis über die Kopfzeile (RecordInvoiceMailDelivery → sent),
 * Queue-Fehlschlag → failed. Texte aus dem Namensraum club.fees.mail.
 */
class ClubFeeNoticeMail extends Mailable implements ShouldQueue {
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $claimId,
        public readonly int $dispatchId,
        public readonly ?int $dunningId = null,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope {
        $claim = $this->claim();

        $dunning = $this->dunning();

        return new Envelope(subject: (string) ($dunning !== null
            ? __('club.fees.mail.subject_dunning', ['number' => $claim->number, 'level' => $dunning->level])
            : __('club.fees.mail.subject', ['number' => $claim->number])));
    }

    public function headers(): Headers {
        return new Headers(text: [\App\Listeners\RecordInvoiceMailDelivery::HEADER => (string) $this->dispatchId]);
    }

    public function content(): Content {
        $claim = $this->claim();
        $params = [
            'name' => (string) ($claim->payer_snapshot['name'] ?? $claim->account->name ?? ''),
            'number' => $claim->number,
            'period' => $claim->period_start->format('d.m.Y') . ' – ' . $claim->period_end->format('d.m.Y'),
            'total' => $claim->total->format(),
            'due' => $claim->due_on->format('d.m.Y'),
        ];
        $dunning = $this->dunning();
        $text = $dunning !== null
            ? (string) __('club.fees.mail.body_dunning', $params + ['open' => $claim->openAmount()->format(), 'pay_until' => $dunning->pay_until?->format('d.m.Y') ?? $claim->due_on->format('d.m.Y'), 'level' => $dunning->level])
            : (string) __('club.fees.mail.body', $params);

        return new Content(
            view: 'mail.document',
            text: 'mail.document-text',
            with: ['html' => nl2br(e($text)), 'text' => $text],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array {
        $claim = $this->claim();
        $renderer = app(ClubFeeNoticePdfRenderer::class);
        $dunning = $this->dunning();
        $bytes = $renderer->output($claim, $dunning);
        DocumentDispatch::query()->withoutGlobalScopes()->whereKey($this->dispatchId)->whereNull('sha256')
            ->update(['sha256' => \CommonToolkit\Helper\Data\CryptoHelper::hash($bytes)]);

        return [Attachment::fromData(static fn(): string => $bytes, $renderer->filename($claim, $dunning))->withMime('application/pdf')];
    }

    public function failed(\Throwable $exception): void {
        $dispatch = DocumentDispatch::query()->withoutGlobalScopes()->find($this->dispatchId);
        $dispatch?->forceFill(['status' => 'failed', 'meta' => [...(array) $dispatch->meta, 'error' => mb_substr($exception->getMessage(), 0, 500)]])->save();
    }

    private function dunning(): ?\App\Models\Club\ClubFeeDunning {
        return $this->dunningId !== null ? \App\Models\Club\ClubFeeDunning::query()->withoutGlobalScopes()->find($this->dunningId) : null;
    }

    private function claim(): ClubFeeClaim {
        /** @var ClubFeeClaim $claim */
        $claim = ClubFeeClaim::query()->withoutGlobalScopes()->with(['account', 'organization'])->findOrFail($this->claimId);

        return $claim;
    }
}
