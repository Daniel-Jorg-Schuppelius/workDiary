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
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingOrigin};
use App\Services\Mail\Contracts\MailIntakeHandler;
use App\Services\Mail\ParsedMessage;

/**
 * Rechnungspostfach (Feature 066, MVP-165; Feature 163, MVP-1107): die Anhänge
 * einer Nachricht laufen gemeinsam durch die Eingangsverarbeitung, damit eine
 * Rechnung genau ein Eingang wird. Dubletten (SHA-256) werden übersprungen;
 * Nachrichten ohne Rechnungsanhang fallen in die Inbox durch.
 */
final class EInvoiceMailIntakeHandler implements MailIntakeHandler {
    public function __construct(private readonly IncomingEInvoiceService $invoices) {}

    public function priority(): int {
        return 20;
    }

    public function handle(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string {
        if (! $connection->einvoice_intake || $message->attachments === []) {
            return null;
        }
        $actor = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->where('id', (int) $connection->created_by)
            ->first();
        if ($actor === null) {
            return null; // ohne zuordenbaren Bearbeiter kein automatischer DMS-Eintrag
        }

        $result = $this->invoices->storeMessage($actor, $message->attachments, new IncomingOrigin($message->fromEmail, $message->messageId));

        return match (true) {
            $result['stored'] + $result['unrecognized'] > 0 => 'einvoice',
            $result['duplicates'] > 0 => 'skipped',
            default => null,
        };
    }
}
