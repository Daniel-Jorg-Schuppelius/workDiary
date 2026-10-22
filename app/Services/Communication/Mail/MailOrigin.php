<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailOrigin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Communication\Mail;

use App\Enums\Communication\CommunicationDirection;
use App\Models\Communication\CommunicationNote;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\InvoiceInquiry;
use App\Services\Mail\ParsedMessage;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Die E-Mail hinter einer Kommunikationsnotiz: eingegangen (Herkunftsverweis
 * des Mail-Eingangs oder Rechnungsnachfrage) oder aus WorkDiary gesendet
 * (Versandverweis der Antwort). Nur eingegangene Mails lassen sich beantworten.
 */
final readonly class MailOrigin {
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    public function __construct(
        public CommunicationDirection $direction,
        public ?string $messageId,
        public ?string $fromEmail,
        public ?string $fromName,
        public array $to,
        public array $cc,
        public ?string $subject,
        public ?CarbonImmutable $at,
        public ?string $mailbox,
        public ?string $replyTo,
        public ?InvoiceInquiry $inquiry,
    ) {}

    public static function fromNote(CommunicationNote $note, ?ExternalReference $reference, ?InvoiceInquiry $inquiry): ?self {
        $payload = $reference !== null ? (array) $reference->payload : [];
        $payloadDirection = (string) ($payload['direction'] ?? CommunicationDirection::Inbound->value);

        if ($note->direction === CommunicationDirection::Outbound) {
            if ($reference === null || $payloadDirection !== CommunicationDirection::Outbound->value) {
                return null;
            }

            return new self(
                direction: CommunicationDirection::Outbound,
                messageId: ParsedMessage::bareMessageId((string) $reference->external_id),
                fromEmail: null,
                fromName: null,
                to: self::addresses($payload['to'] ?? []),
                cc: self::addresses($payload['cc'] ?? []),
                subject: self::text($payload['subject'] ?? null),
                at: self::moment($payload['sent_at'] ?? null) ?? ($reference->synced_at?->toImmutable()),
                mailbox: null,
                replyTo: self::text($payload['reply_to'] ?? null),
                inquiry: null,
            );
        }

        if ($note->direction !== CommunicationDirection::Inbound) {
            return null;
        }

        if ($reference !== null && $payloadDirection === CommunicationDirection::Inbound->value) {
            $from = (array) ($payload['from'] ?? []);

            return new self(
                direction: CommunicationDirection::Inbound,
                messageId: ParsedMessage::bareMessageId((string) ($payload['message_id'] ?? $reference->external_id)),
                fromEmail: self::text($from['email'] ?? null),
                fromName: self::text($from['name'] ?? null),
                to: [],
                cc: [],
                subject: self::text($payload['subject'] ?? null),
                at: self::moment($payload['received_at'] ?? null) ?? $note->occurred_at->toImmutable(),
                mailbox: self::text($payload['mailbox'] ?? null),
                replyTo: null,
                inquiry: $inquiry,
            );
        }

        if ($inquiry !== null) {
            return new self(
                direction: CommunicationDirection::Inbound,
                messageId: ParsedMessage::bareMessageId((string) $inquiry->message_id),
                fromEmail: self::text($inquiry->from_email),
                fromName: self::text($inquiry->from_name),
                to: [],
                cc: [],
                subject: self::text($inquiry->subject),
                at: $inquiry->received_at->toImmutable(),
                mailbox: null,
                replyTo: null,
                inquiry: $inquiry,
            );
        }

        return null;
    }

    public function isInbound(): bool {
        return $this->direction === CommunicationDirection::Inbound;
    }

    /** „Name <adresse>“, nur der Name oder nur die Adresse — was vorliegt. */
    public function senderLabel(): string {
        if ($this->fromName !== null && $this->fromEmail !== null) {
            return $this->fromName . ' <' . $this->fromEmail . '>';
        }

        return $this->fromName ?? $this->fromEmail ?? '';
    }

    private static function text(mixed $value): ?string {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text !== '' ? $text : null;
    }

    /** @return list<string> */
    private static function addresses(mixed $value): array {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '', $value), static fn (string $v): bool => $v !== ''));
    }

    private static function moment(mixed $value): ?CarbonImmutable {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
