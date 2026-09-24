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
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use App\Services\Mail\Contracts\MailIntakeHandler;
use App\Services\Mail\ParsedMessage;

/**
 * E-Rechnungs-Postfach (Feature 066, MVP-165): XML-/PDF-Anhänge laufen durch
 * dieselbe Eingangsverarbeitung wie der Upload; Dubletten (SHA-256) werden
 * übersprungen, nicht lesbare Nachrichten fallen in die Inbox durch.
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

        $created = 0;
        $duplicates = 0;
        foreach ($message->attachments as $attachment) {
            $name = strtolower($attachment->filename);
            $isCandidate = str_contains($attachment->mime, 'xml') || str_contains($attachment->mime, 'pdf')
                || str_ends_with($name, '.xml') || str_ends_with($name, '.pdf');
            if (! $isCandidate) {
                continue;
            }

            $result = $this->invoices->storeIncoming($actor, $attachment->content, $attachment->mime, null, 'mail', null, $attachment->filename);
            if ($result['status'] === 'created') {
                $created++;
            } elseif ($result['status'] === 'duplicate') {
                $duplicates++;
            }
        }

        return $created > 0 ? 'einvoice' : ($duplicates > 0 ? 'skipped' : null);
    }
}
