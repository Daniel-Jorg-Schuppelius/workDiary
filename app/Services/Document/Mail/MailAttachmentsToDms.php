<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAttachmentsToDms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document\Mail;

use App\Enums\Document\DocumentType;
use App\Models\Document\Document;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\User;
use App\Services\Document\DocumentService;
use App\Services\Mail\{MailAttachmentStore, MailIntakeService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Beim Intake persistierte Mail-Anhänge ins DMS übernehmen (MVP-343), idempotent je Message-ID + Index. */
class MailAttachmentsToDms {
    public const DMS_EXTERNAL_TYPE = 'message-attachment';

    public function __construct(
        private readonly DocumentService $documents,
        private readonly MailAttachmentStore $store,
    ) {}

    /** @return list<Document> */
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
        foreach ($this->store->storedAttachments($item) as $meta) {
            $externalId = $messageId . '#' . (int) ($meta['index'] ?? 0);

            $alreadyImported = ExternalReference::query()
                ->forPlugin($item->organization_id, MailIntakeService::PLUGIN_ID, self::DMS_EXTERNAL_TYPE)
                ->forExternalId($externalId)
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
}
