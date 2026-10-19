<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceReceivedTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Automation;

use App\Automation\Triggers\RuleTrigger;
use App\Models\Invoicing\IncomingEInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Auslöser „Rechnungseingang eingegangen“ (Phase 150, MVP-1209): feuert nach
 * dem Anlegen eines Eingangs auf jedem Kanal und nach der Nacherkennung, vor
 * dem Abgleich der Gegenpartei. Der Kontext ergänzt normalisierte Namen,
 * Absender-Domain, Betreff, Anhangsnamen und Land, damit Bedingungen ohne
 * Rücksicht auf Schreibweise greifen.
 */
final class IncomingInvoiceReceivedTrigger implements RuleTrigger {
    public const KEY = 'incoming_invoice.received';

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('automation.trigger.incoming_invoice_received');
    }

    /** @return array<string, mixed> */
    public function exampleConditions(): array {
        return ['all' => [['field' => 'seller_name_key', 'op' => '=', 'value' => 'ckonverter.de']]];
    }

    /** @return array<string, mixed> */
    public function context(Model $subject): array {
        $data = $subject->toArray();
        if (! $subject instanceof IncomingEInvoice) {
            return $data;
        }
        $summary = (array) $subject->summary;
        $sender = (string) $subject->sender_email;

        return $data + [
            'seller_name_key' => IncomingEInvoice::nameKey($subject->seller_name),
            'buyer_name_key' => IncomingEInvoice::nameKey($subject->buyer_name),
            'party_name' => $subject->counterpartyName(),
            'party_name_key' => IncomingEInvoice::nameKey($subject->counterpartyName()),
            'sender_domain' => str_contains($sender, '@') ? mb_strtolower((string) substr((string) strrchr($sender, '@'), 1)) : '',
            'mail_subject' => (string) ($summary['mail_subject'] ?? ''),
            'attachment_names' => implode("\n", array_map(strval(...), (array) ($summary['mail_attachments'] ?? []))),
            'file_name' => (string) ($summary['file_name'] ?? ''),
            'country' => mb_strtoupper((string) ($summary['seller_country'] ?? '')),
            'kind_hint' => (string) ($summary['kind_hint'] ?? ''),
        ];
    }
}
