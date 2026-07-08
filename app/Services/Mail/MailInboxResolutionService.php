<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailInboxResolutionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Document\DocumentType;
use App\Models\{CommunicationNote, Customer, Document, ExternalReference, IntegrationInboxItem, User};
use App\Services\Communication\CommunicationNoteService;
use App\Services\Document\DocumentService;
use App\Services\Integration\InboxActionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Löst einen zugeordneten E-Mail-Inbox-Eintrag in einen Eintrag im
 * Kommunikationsprotokoll auf (Feature 056, MVP-117): erzeugt eine eingehende
 * {@see CommunicationNote} (Kanal E-Mail) am Kunden aus dem Mail-Snapshot und
 * schließt den Inbox-Fall über den generischen {@see InboxActionService}
 * (dauerhafte Message-ID→Notiz-Bindung via ExternalReference + Audit).
 *
 * Anhänge (Rang 7): Beim Buchen werden die beim Intake persistierten Anhänge an
 * die Notiz gehängt (Option a). Zusätzlich kann jeder Anhang explizit ins DMS
 * übernommen werden ({@see importAttachmentsToDms}, Option b) — idempotent, mit
 * Herkunftsvermerk (Message-ID).
 */
class MailInboxResolutionService {
    /** ExternalReference-Diskriminator für ins DMS übernommene Anhänge. */
    public const DMS_EXTERNAL_TYPE = 'message-attachment';

    public function __construct(
        private readonly CommunicationNoteService $notes,
        private readonly InboxActionService $actions,
        private readonly DocumentService $documents,
    ) {}

    public function bookAsCommunicationNote(IntegrationInboxItem $item, Customer $customer, User $actor, bool $attachFiles = true): CommunicationNote {
        if ($item->plugin_id !== MailIntakeService::PLUGIN_ID) {
            throw new RuntimeException('Kein E-Mail-Inbox-Eintrag.');
        }

        $snapshot = $item->remote_snapshot ?? [];

        $note = $this->notes->create($customer, $actor, [
            'type' => CommunicationNoteType::Email->value,
            'direction' => CommunicationDirection::Inbound->value,
            'subject' => (string) ($snapshot['subject'] ?? $item->display_title ?? ''),
            'body' => (string) ($snapshot['body'] ?? ''),
            'occurred_at' => $item->occurred_at?->toIso8601String() ?? 'now',
        ]);

        // Anhänge der Mail gehören zur Korrespondenz → an die Notiz hängen.
        if ($attachFiles) {
            $this->attachStoredFilesToNote($item, $note, $actor);
        }

        // Schließt den Inbox-Fall (Status + Audit) und bindet Message-ID→Notiz.
        $this->actions->assignTo($item, $note);

        return $note;
    }

    /**
     * „Als Service-Ticket buchen" (Feature 065, P2): erzeugt aus einem
     * Mail-Inbox-Eintrag ein Ticket in der Queue des Eingangspostfachs
     * (Source email) samt Erst-Nachricht mit Message-ID fürs Threading.
     */
    public function bookAsServiceTicket(IntegrationInboxItem $item, ?Customer $customer, User $actor, ?\App\Models\ServiceQueue $queue = null): \App\Models\ServiceTicket {
        if ($item->plugin_id !== MailIntakeService::PLUGIN_ID) {
            throw new RuntimeException('Kein E-Mail-Inbox-Eintrag.');
        }

        $snapshot = $item->remote_snapshot ?? [];
        $queueId = $queue->id ?? (isset($snapshot['service_queue_id']) ? (int) $snapshot['service_queue_id'] : null);

        $ticket = app(\App\Services\ServiceTicket\ServiceTicketService::class)->create(
            $item->organization()->firstOrFail(),
            $actor,
            array_filter([
                'title' => (string) ($snapshot['subject'] ?? $item->display_title ?? __('Neues Ticket')),
                'description' => (string) ($snapshot['body'] ?? ''),
                'customer_id' => $customer?->id,
                'queue_id' => $queueId,
                'source' => \App\Enums\ServiceTicket\ServiceTicketSource::Email->value,
                'source_reference' => (string) ($snapshot['mail_message_id'] ?? $item->external_id ?? ''),
            ], fn($value) => $value !== null && $value !== ''),
        );

        // Erst-Nachricht mit Message-ID — spätere Antworten threaden hierauf.
        \App\Models\ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => \App\Enums\ServiceTicket\TicketMessageKind::PublicReply->value,
            'subject' => (string) ($snapshot['subject'] ?? ''),
            'body' => trim(strip_tags((string) ($snapshot['body'] ?? ''))),
            'channel' => 'mail',
            'message_id' => (string) ($snapshot['mail_message_id'] ?? $item->external_id ?? '') ?: null,
        ]);

        $this->actions->assignTo($item, $ticket);

        return $ticket;
    }

    /**
     * „Ins DMS übernehmen" (Option b): jeden persistierten Anhang als eigenes
     * Dokument (inkl. Erst-Version) mit Herkunftsvermerk anlegen. Idempotent über
     * eine {@see ExternalReference} je (Message-ID, Anhang-Index) — ein zweiter
     * Aufruf legt nichts doppelt an.
     *
     * @return list<Document>
     */
    public function importAttachmentsToDms(IntegrationInboxItem $item, User $actor, ?Model $documentable = null): array {
        if ($item->plugin_id !== MailIntakeService::PLUGIN_ID) {
            throw new RuntimeException('Kein E-Mail-Inbox-Eintrag.');
        }

        $messageId = (string) $item->external_id;
        $snapshot = (array) ($item->remote_snapshot ?? []);
        $subject = (string) ($snapshot['subject'] ?? '');

        $created = [];
        foreach ($this->storedAttachments($item) as $meta) {
            $externalId = $messageId . '#' . (int) ($meta['index'] ?? 0);

            $alreadyImported = ExternalReference::query()
                ->where('organization_id', $item->organization_id)
                ->where('plugin_id', MailIntakeService::PLUGIN_ID)
                ->where('external_type', self::DMS_EXTERNAL_TYPE)
                ->where('external_id', $externalId)
                ->exists();
            if ($alreadyImported) {
                continue; // schon übernommen (Doppelauflösung)
            }

            $disk = (string) ($meta['disk'] ?? MailAttachmentStore::DISK);
            $source = (string) ($meta['stored_path'] ?? '');
            if ($source === '' || ! Storage::disk($disk)->exists($source)) {
                continue;
            }

            $mime = trim((string) ($meta['mime'] ?? ''));
            $upload = new UploadedFile(
                Storage::disk($disk)->path($source),
                (string) ($meta['original_name'] ?? 'anhang'),
                $mime !== '' ? $mime : null,
                null,
                true, // programmatischer Upload → is_uploaded_file()-Check überspringen
            );

            // Eingangs-E-Rechnung erkennen (Nachtrag 045b): XML/ZUGFeRD-PDF →
            // Typ Rechnung + sprechender Titel; sonst wie bisher Typ Sonstiges.
            $documentType = DocumentType::Other;
            $title = (string) ($meta['original_name'] ?? 'anhang');
            $parsedInvoice = app(\App\Services\Invoicing\EInvoice\IncomingEInvoiceService::class)->parse(
                (string) Storage::disk($disk)->get($source),
                $mime !== '' ? $mime : null,
                Storage::disk($disk)->path($source),
            );
            if ($parsedInvoice !== null) {
                $documentType = DocumentType::Invoice;
                $title = (string) __('E-Rechnung :number — :seller', [
                    'number' => $parsedInvoice->getId(),
                    'seller' => $parsedInvoice->getSeller()->getName(),
                ]);
            }

            $document = $this->documents->create($documentable, $actor, [
                'title' => $title,
                'document_type' => $documentType->value,
                'description' => (string) __('mail.dms.origin', ['subject' => $subject, 'message_id' => $messageId]),
            ], $upload);

            ExternalReference::query()->create([
                'organization_id' => $item->organization_id,
                'plugin_id' => MailIntakeService::PLUGIN_ID,
                'external_type' => self::DMS_EXTERNAL_TYPE,
                'external_id' => $externalId,
                'referenceable_type' => $document->getMorphClass(),
                'referenceable_id' => $document->getKey(),
                'payload' => ['original_name' => $meta['original_name'] ?? null, 'message_id' => $messageId],
                'synced_at' => now(),
            ]);

            $created[] = $document;
        }

        return $created;
    }

    /**
     * Kopiert die beim Intake persistierten (angenommenen) Anhänge als
     * {@see \App\Models\Attachment} an die Notiz. Die Quelle im `mail-intake/`
     * bleibt erhalten (für eine spätere DMS-Übernahme / Retention-Purge).
     */
    private function attachStoredFilesToNote(IntegrationInboxItem $item, CommunicationNote $note, User $actor): int {
        $count = 0;
        foreach ($this->storedAttachments($item) as $meta) {
            $disk = (string) ($meta['disk'] ?? MailAttachmentStore::DISK);
            $source = (string) ($meta['stored_path'] ?? '');
            if ($source === '' || ! Storage::disk($disk)->exists($source)) {
                continue;
            }

            $ext = strtolower(pathinfo((string) ($meta['original_name'] ?? ''), PATHINFO_EXTENSION));
            $target = 'attachments/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
            Storage::disk('local')->put($target, (string) Storage::disk($disk)->get($source));

            $mime = trim((string) ($meta['mime'] ?? ''));
            $note->attachments()->create([
                'organization_id' => $note->organization_id,
                'user_id' => $actor->id,
                'disk' => 'local',
                'path' => $target,
                'original_name' => (string) ($meta['original_name'] ?? 'anhang'),
                'mime' => $mime !== '' ? $mime : null,
                'size' => (int) ($meta['size'] ?? 0),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Die beim Intake tatsächlich persistierten Anhänge (abgelehnte fehlen).
     *
     * @return list<array<string, mixed>>
     */
    private function storedAttachments(IntegrationInboxItem $item): array {
        $all = (array) (($item->remote_snapshot ?? [])['attachments'] ?? []);

        return array_values(array_filter(
            $all,
            static fn ($a): bool => is_array($a) && ($a['stored'] ?? false) === true,
        ));
    }
}
