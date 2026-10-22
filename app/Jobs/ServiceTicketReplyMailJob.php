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

use App\Enums\ServiceTicket\{TicketMessageDeliveryStatus, TicketMessageKind};
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceTicketMessage;
use App\Services\Mail\MailSignature;
use CommonToolkit\Helper\Data\EmailHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Cache, Mail};

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
            ->where('delivery_status', TicketMessageDeliveryStatus::Queued)
            ->update(['delivery_status' => TicketMessageDeliveryStatus::Failed]);
    }

    public function handle(): void {
        // Höchstens einmal versenden (Sicherheitsaudit 2026-10-21, li-8): unter der Sperre neu lesen, „versendet“
        // vor dem Versand setzen und nur bei einem Versandfehler zurücknehmen — eine Wiederholung nach einem
        // gescheiterten Statusschreiben schickt die Antwort nicht ein zweites Mal.
        Cache::lock('ticket-reply-mail:' . $this->messageId, 120)->block(30, function (): void {
            $this->send();
        });
    }

    private function send(): void {
        $message = ServiceTicketMessage::query()->withoutGlobalScopes()->find($this->messageId);
        if ($message === null || $message->delivery_status === TicketMessageDeliveryStatus::Sent) {
            return;
        }

        // Typgarantie — NIE interne Notizen oder Systemereignisse versenden.
        if ($message->kind !== TicketMessageKind::PublicReply) {
            throw new \RuntimeException('Nur öffentliche Antworten dürfen versendet werden (kind=' . $message->kind->value . ').');
        }

        $recipients = array_values(array_filter((array) ($message->to ?? []), fn($mail) => EmailHelper::isEmail($mail)));
        if ($recipients === []) {
            $message->update(['delivery_status' => TicketMessageDeliveryStatus::Failed]);

            return;
        }

        $ticket = $message->ticket()->withoutGlobalScopes()->firstOrFail();
        $subject = sprintf('[%s] %s', $ticket->ticket_no, $message->subject ?? $ticket->title);

        // Signatur und Fußzeile wie bei der Antwort aus der Notiz; Portal-Antworten von Kundenkonten bleiben ohne.
        $author = $message->author;
        $text = $message->body;
        if ($author instanceof User) {
            $text .= "\n\n" . app(MailSignature::class)->blockFor($author, $ticket->organization()->withoutGlobalScopes()->first());
        }

        $previous = $message->delivery_status;
        $message->update(['delivery_status' => TicketMessageDeliveryStatus::Sent]);
        try {
            Mail::raw($text, function ($mail) use ($recipients, $subject): void {
                $mail->to($recipients)->subject($subject);
            });
        } catch (\Throwable $e) {
            $message->update(['delivery_status' => $previous]);

            throw $e;
        }
    }
}
