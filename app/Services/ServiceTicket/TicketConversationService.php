<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketConversationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketMessageKind};
use App\Jobs\ServiceTicketReplyMailJob;
use App\Models\{ServiceTicket, ServiceTicketMessage, User};
use App\Services\Mail\MailAttachmentStore;
use App\Support\Filename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

/**
 * Konversation am Ticket (Feature 065, MVP-152). Öffentlich vs. intern
 * ist eine TYPFRAGE: reply() erzeugt public_reply (einzig versandfähig),
 * note() erzeugt internal_note (nie kundensichtbar, nie versendbar) —
 * getrennte Methoden, getrennte Rechte, getrennte Routen.
 *
 * Anhänge (MVP-152): jede Nachricht kann optionale Datei-Anhänge tragen;
 * die Kundensichtbarkeit regelt `customer_visible` je Anhang — Uploads an
 * interne Notizen bleiben intern, Portal-/Inbound-Anhänge sind kundensichtbar.
 */
class TicketConversationService {
    /**
     * Öffentliche Antwort — landet beim Kunden (Versand-Job nur hier).
     *
     * @param array<int, string> $to
     * @param list<UploadedFile> $files
     */
    public function reply(ServiceTicket $ticket, User $author, string $body, array $to = [], ?string $subject = null, string $channel = 'manual', array $files = []): ServiceTicketMessage {
        $body = trim(strip_tags($body));
        if ($body === '') {
            throw new \InvalidArgumentException((string) __('Die Antwort braucht einen Inhalt.'));
        }

        return DB::transaction(function () use ($ticket, $author, $body, $to, $subject, $channel, $files): ServiceTicketMessage {
            $message = ServiceTicketMessage::query()->create([
                'organization_id' => $ticket->organization_id,
                'service_ticket_id' => $ticket->id,
                'kind' => TicketMessageKind::PublicReply->value,
                'author_type' => $author->getMorphClass(),
                'author_id' => $author->id,
                'to' => $to !== [] ? array_values($to) : null,
                'subject' => $subject,
                'body' => $body,
                'channel' => $channel,
                'delivery_status' => $to !== [] ? 'queued' : null,
            ]);

            // Antwort ist kundensichtbar → ihre Anhänge auch.
            $this->attachUploadedFiles($message, $files, $author, customerVisible: true);

            $ticket->audit('service_ticket.replied', ['message_id' => $message->id]);

            if ($to !== []) {
                // afterCommit: der Dispatch läuft in einer Transaktion — bei
                // Nicht-DB-Queue-Drivern (Redis/SQS) fände der Job die
                // Nachricht sonst vor dem Commit nicht und returnte still.
                ServiceTicketReplyMailJob::dispatch($message->id)->afterCommit();
            }

            return $message;
        });
    }

    /**
     * Interne Notiz — nie kundensichtbar, nie versendbar (Typgarantie).
     *
     * @param list<UploadedFile> $files
     */
    public function note(ServiceTicket $ticket, User $author, string $body, array $files = []): ServiceTicketMessage {
        $body = trim(strip_tags($body));
        if ($body === '') {
            throw new \InvalidArgumentException((string) __('Die Notiz braucht einen Inhalt.'));
        }

        $message = ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::InternalNote->value,
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->id,
            'body' => $body,
            'channel' => 'manual',
        ]);

        // Anhänge interner Notizen bleiben intern (customer_visible = false).
        $this->attachUploadedFiles($message, $files, $author, customerVisible: false);

        $ticket->audit('service_ticket.noted', ['message_id' => $message->id]);

        return $message;
    }

    /** Systemereignis (Statuswechsel, SLA, Zuordnung) für die Timeline. */
    public function systemEvent(ServiceTicket $ticket, string $body): ServiceTicketMessage {
        return ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::SystemEvent->value,
            'body' => $body,
            'channel' => 'system',
        ]);
    }

    /**
     * Eingehende Kundennachricht (Mail/Portal): anhängen; ein wartendes
     * Ticket geht zurück in Bearbeitung (waiting → in_progress).
     *
     * @param list<UploadedFile> $files
     */
    public function inbound(ServiceTicket $ticket, string $body, string $channel, ?string $messageId = null, ?string $inReplyTo = null, ?string $subject = null, ?object $author = null, array $files = []): ServiceTicketMessage {
        $message = ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::PublicReply->value,
            'author_type' => $author instanceof \Illuminate\Database\Eloquent\Model ? $author->getMorphClass() : null,
            'author_id' => $author instanceof \Illuminate\Database\Eloquent\Model ? $author->getKey() : null,
            'subject' => $subject,
            'body' => trim(strip_tags($body)),
            'channel' => $channel,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
        ]);

        // Vom Kunden eingereichte Dateien sind naturgemäß kundensichtbar.
        $this->attachUploadedFiles($message, $files, $author instanceof User ? $author : null, customerVisible: true);

        // ticket.customerReplied (P3): Bearbeiter informieren.
        if ($ticket->assigned_to_user_id !== null) {
            $assignee = \App\Models\User::query()->find($ticket->assigned_to_user_id);
            if ($assignee !== null) {
                app(\App\Services\Notification\NotificationDispatcher::class)->notify(
                    \App\Enums\Notification\NotificationEvent::TicketCustomerReplied,
                    $ticket,
                    $assignee,
                    [
                        'title' => (string) __('Kundenantwort zu Ticket :no', ['no' => $ticket->ticket_no]),
                        'title_key' => 'Kundenantwort zu Ticket :no',
                        'title_params' => ['no' => $ticket->ticket_no],
                        'body' => mb_substr($message->body, 0, 200),
                        'url' => route('service-tickets.show', $ticket),
                    ],
                    dedup: false,
                );
            }
        }

        if ($ticket->status->isWaiting()) {
            // Kundenreaktion beendet das Warten — zurück in Bearbeitung.
            $ticket->forceFill([
                'status' => ServiceTicketStatus::InProgress->value,
                'wait_reason' => null,
                'wait_until' => null,
                'wait_owner_id' => null,
            ])->save();
            $ticket->audit('service_ticket.resumed', ['origin' => 'inbound_message']);
        }

        return $message;
    }

    /**
     * Beim Mail-Intake persistierte Snapshot-Anhänge ({@see MailAttachmentStore})
     * an eine Nachricht kopieren — idempotent je (Nachricht, Dateiname, Größe),
     * die Quelle im `mail-intake/` bleibt für die spätere Auflösung erhalten.
     * Anhänge einer eingehenden Kundenmail sind kundensichtbar.
     *
     * @param  list<mixed>  $storedMetas  rohe Snapshot-Metadaten (nur `stored = true` wird übernommen)
     */
    public function attachStoredMailAttachments(ServiceTicketMessage $message, array $storedMetas): int {
        $count = 0;
        foreach ($storedMetas as $meta) {
            if (! is_array($meta) || ($meta['stored'] ?? false) !== true) {
                continue;
            }

            $disk = (string) ($meta['disk'] ?? MailAttachmentStore::DISK);
            $source = (string) ($meta['stored_path'] ?? '');
            if ($source === '' || ! Storage::disk($disk)->exists($source)) {
                continue;
            }

            $originalName = (string) ($meta['original_name'] ?? 'anhang');
            $size = (int) ($meta['size'] ?? 0);
            $exists = $message->attachments()
                ->where('original_name', $originalName)
                ->where('size', $size)
                ->exists();
            if ($exists) {
                continue; // schon übernommen (Doppelverarbeitung)
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $target = 'attachments/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
            Storage::disk('local')->put($target, (string) Storage::disk($disk)->get($source));

            $mime = trim((string) ($meta['mime'] ?? ''));
            $message->attachments()->create([
                'organization_id' => $message->organization_id,
                'disk' => 'local',
                'path' => $target,
                'original_name' => $originalName,
                'mime' => $mime !== '' ? $mime : null,
                'size' => $size,
                'customer_visible' => true,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Hochgeladene Dateien als Anhänge speichern (gleiche Ablage wie
     * {@see \App\Http\Controllers\AttachmentController::store()}: Disk
     * `local`, UUID-Dateiname, sanitisierter Originalname). Öffentlich für
     * Ticket-Anhänge bei der Portal-Anlage (MVP-152); die Datei-Policy
     * (Typ/Größe) prüft der jeweilige Controller VOR dem Aufruf.
     *
     * @param list<UploadedFile> $files
     */
    public function attachUploadedFiles(ServiceTicket|ServiceTicketMessage $target, array $files, ?User $author, bool $customerVisible): void {
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
            $folder = 'attachments/' . now()->format('Y/m');
            $filename = Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
            $path = $file->storeAs($folder, $filename, 'local');

            $target->attachments()->create([
                'organization_id' => $target->organization_id,
                'user_id' => $author?->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Filename::sanitize($file->getClientOriginalName()),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'customer_visible' => $customerVisible,
            ]);
        }
    }
}
