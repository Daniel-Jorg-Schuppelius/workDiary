<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EInvoiceMailIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mail;

use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingOrigin};
use App\Services\Mail\Contracts\MailIntakeHandler;
use App\Services\Mail\ParsedMessage;
use App\Services\Platform\IntakeActor;

/**
 * Rechnungspostfach (Feature 066, MVP-165; Feature 163, MVP-1107): die Anhänge
 * einer Nachricht laufen gemeinsam durch die Eingangsverarbeitung, damit eine
 * Rechnung genau ein Eingang wird. Dubletten (SHA-256) werden übersprungen;
 * Nachrichten ohne Rechnungsanhang fallen in die Inbox durch. Kam die Mail
 * schon früher als Inbox-Eintrag an (Nachholen), wird dieser erledigt.
 */
final class EInvoiceMailIntakeHandler implements MailIntakeHandler {
    public function __construct(
        private readonly IncomingEInvoiceService $invoices,
        private readonly MailToIncomingInvoice $inbox,
    ) {}

    public function priority(): int {
        return 20;
    }

    public function handle(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string {
        if (! $connection->einvoice_intake || $message->attachments === []) {
            return null;
        }
        $actor = IntakeActor::resolve((int) $connection->organization_id, $connection->created_by);
        if ($actor === null) {
            return null; // Organisation ohne Person: kein automatischer DMS-Eintrag
        }

        $result = $this->invoices->storeMessage($actor, $message->attachments, new IncomingOrigin($message->fromEmail, $message->messageId));
        $incoming = $result['incomings'][0] ?? null;
        if ($incoming !== null) {
            $this->inbox->resolveOpenInboxItem((int) $connection->organization_id, $message->messageId, $incoming);
        }

        return match (true) {
            $result['stored'] + $result['unrecognized'] > 0 => 'einvoice',
            $result['duplicates'] > 0 => 'skipped',
            default => null,
        };
    }
}
