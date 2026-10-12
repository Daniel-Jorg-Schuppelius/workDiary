<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailToIncomingInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mail;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Integration\InboxActionService;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingOrigin};
use App\Services\Mail\{MailAttachment, MailAttachmentStore, MailIntakeService};
use Illuminate\Support\Facades\Storage;

/**
 * Mail-Inbox-Eintrag als Eingangsrechnung übernehmen (MVP-1144): die beim
 * Eingang abgelegten Anhänge laufen durch dieselbe Verarbeitung wie im
 * Rechnungspostfach — für Mails, die vor dem Einschalten des Schalters oder in
 * einem allgemeinen Postfach ankamen. „Anhänge ins DMS“ legt dagegen nur
 * Dokumente an, keinen Eingang.
 */
final class MailToIncomingInvoice {
    public function __construct(
        private readonly IncomingEInvoiceService $invoices,
        private readonly InboxActionService $actions,
    ) {}

    /**
     * @return array{stored: int, unrecognized: int, duplicates: int, infected: int, candidates: int, incomings: list<IncomingEInvoice>}
     */
    public function import(IntegrationInboxItem $item, User $actor): array {
        $snapshot = (array) ($item->remote_snapshot ?? []);
        $attachments = [];
        foreach ((array) ($snapshot['attachments'] ?? []) as $meta) {
            if (! is_array($meta) || ($meta['stored'] ?? false) !== true) {
                continue;
            }
            $disk = (string) ($meta['disk'] ?? MailAttachmentStore::DISK);
            $path = (string) ($meta['stored_path'] ?? '');
            if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                continue;
            }
            $attachments[] = new MailAttachment(
                filename: (string) ($meta['original_name'] ?? 'anhang'),
                mime: (string) ($meta['mime'] ?? 'application/octet-stream'),
                content: (string) Storage::disk($disk)->get($path),
            );
        }

        $messageId = (string) ($snapshot['message_id'] ?? $item->external_id ?? '');
        $from = (string) (((array) ($snapshot['from'] ?? []))['email'] ?? '');
        $result = $this->invoices->storeMessage($actor, $attachments, new IncomingOrigin($from, $messageId));

        $incoming = $result['incomings'][0] ?? null;
        if ($incoming instanceof IncomingEInvoice) {
            $this->actions->markResolved($item, IntegrationInboxStatus::ResolvedCreated, $incoming);
        } elseif ($result['duplicates'] > 0) {
            $this->actions->markResolved($item, IntegrationInboxStatus::ResolvedLinked); // schon im Rechnungseingang
        }

        return $result;
    }

    /**
     * Erledigt den offenen Inbox-Eintrag derselben Mail, sobald das
     * Rechnungspostfach ihre Rechnung übernommen hat (Nachholen, E3).
     */
    public function resolveOpenInboxItem(int $organizationId, string $messageId, IncomingEInvoice $incoming): void {
        $item = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', MailIntakeService::PLUGIN_ID)
            ->where('dedupe_key', 'message:' . $messageId)
            ->where('status', IntegrationInboxStatus::Open)
            ->first();
        if ($item instanceof IntegrationInboxItem) {
            $this->actions->markResolved($item, IntegrationInboxStatus::ResolvedCreated, $incoming);
        }
    }
}
