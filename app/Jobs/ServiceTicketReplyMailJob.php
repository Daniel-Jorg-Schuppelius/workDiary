<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketReplyMailJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ServiceTicket\TicketMessageKind;
use App\Models\ServiceTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Mail;

/**
 * Versand einer Ticket-Antwort (Feature 065, MVP-152). HARTE Typ-Garantie:
 * ausschließlich kind=public_reply verlässt das Haus — jede andere
 * Nachricht wird abgewiesen (DoD „Notiz kann nie versendet werden").
 */
class ServiceTicketReplyMailJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** SMTP-Aussetzer überbrücken statt beim ersten Fehler aufzugeben. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $messageId) {}

    /**
     * Nach dem LETZTEN fehlgeschlagenen Versuch den Status sichtbar machen —
     * sonst zeigt das Ticket dauerhaft „queued" und niemand merkt, dass die
     * Kundenantwort nie rausging.
     */
    public function failed(?\Throwable $exception): void {
        ServiceTicketMessage::query()->withoutGlobalScopes()
            ->whereKey($this->messageId)
            ->where('delivery_status', 'queued')
            ->update(['delivery_status' => 'failed']);
    }

    public function handle(): void {
        $message = ServiceTicketMessage::query()->withoutGlobalScopes()->find($this->messageId);
        if ($message === null) {
            return;
        }

        // Typgarantie — NIE interne Notizen oder Systemereignisse versenden.
        if ($message->kind !== TicketMessageKind::PublicReply) {
            throw new \RuntimeException('Nur öffentliche Antworten dürfen versendet werden (kind=' . $message->kind->value . ').');
        }

        $recipients = array_values(array_filter((array) ($message->to ?? []), fn($mail) => filter_var($mail, FILTER_VALIDATE_EMAIL) !== false));
        if ($recipients === []) {
            $message->update(['delivery_status' => 'failed']);

            return;
        }

        $ticket = $message->ticket()->withoutGlobalScopes()->firstOrFail();
        $subject = sprintf('[%s] %s', $ticket->ticket_no, $message->subject ?? $ticket->title);

        Mail::raw($message->body, function ($mail) use ($recipients, $subject): void {
            $mail->to($recipients)->subject($subject);
        });

        $message->update(['delivery_status' => 'sent']);
    }
}
