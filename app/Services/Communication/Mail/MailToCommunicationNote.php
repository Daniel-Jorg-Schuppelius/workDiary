<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailToCommunicationNote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Communication\Mail;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\User;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Integration\InboxActionService;
use App\Services\Mail\{MailAttachmentStore, MailIntakeService};
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Mail-Inbox-Eintrag als Kommunikationsnotiz buchen (Feature 117); Anhänge hängen an der Notiz. */
class MailToCommunicationNote {
    public function __construct(
        private readonly CommunicationNoteService $notes,
        private readonly InboxActionService $actions,
        private readonly MailAttachmentStore $store,
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
     * Kopiert die beim Intake persistierten (angenommenen) Anhänge als
     * {@see \App\Models\Attachments\Attachment} an die Notiz. Die Quelle im `mail-intake/`
     * bleibt erhalten (für eine spätere DMS-Übernahme / Retention-Purge).
     */
    private function attachStoredFilesToNote(IntegrationInboxItem $item, CommunicationNote $note, User $actor): int {
        $count = 0;
        foreach ($this->store->storedAttachments($item) as $meta) {
            $disk = (string) ($meta['disk'] ?? MailAttachmentStore::DISK);
            $source = (string) ($meta['stored_path'] ?? '');
            if ($source === '' || ! Storage::disk($disk)->exists($source)) {
                continue;
            }

            // Ablage über den kanonischen FileAttacher (Vollaudit 2026-07, M46).
            $mime = trim((string) ($meta['mime'] ?? ''));
            app(\App\Services\Attachments\FileAttacher::class)->storeContent(
                $note,
                (string) Storage::disk($disk)->get($source),
                (string) ($meta['original_name'] ?? 'anhang'),
                $mime !== '' ? $mime : null,
                $actor->id,
                ['organization_id' => $note->organization_id],
            );
            $count++;
        }

        return $count;
    }
}
