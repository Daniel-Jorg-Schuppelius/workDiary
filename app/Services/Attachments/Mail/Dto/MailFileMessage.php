<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileMessage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments\Mail\Dto;

use App\Enums\Attachments\MailFileFormat;

/**
 * Einheitliches Ergebnis des Mail-Lesers (Feature 204, MVP-1652) für `.eml`
 * und `.msg`: Ansicht, Suche und Übernahme der Anhänge kennen nur dieses
 * Ergebnis, nicht die Herkunft.
 *
 * Text ist der Klartext-Körper; hat die Mail nur HTML, steht hier der über
 * `StringHelper::htmlToText()` gewonnene Text ({@see $textFromHtml}). Das
 * Original-HTML liegt getrennt in {@see $html} und wird nur abgeschottet
 * ausgeliefert; im Cache steht es nicht.
 */
final class MailFileMessage {
    /** Pfad eines Anhangs: Index je Ebene, höchstens drei Ebenen („2“, „1.0“, „1.0.3“). */
    public const string PART_PATH_PATTERN = '/^\d{1,4}(?:\.\d{1,4}){0,2}$/';

    public function __construct(
        public readonly MailFileFormat $format,
        public readonly string $subject,
        /** @var array{name: string, address: string}|null */
        public readonly ?array $from = null,
        /** @var list<array{name: string, address: string}> */
        public readonly array $to = [],
        /** @var list<array{name: string, address: string}> */
        public readonly array $cc = [],
        /** @var list<array{name: string, address: string}> */
        public readonly array $bcc = [],
        /** Zeitpunkt als Unix-Sekunden (UTC) — eine Zahl, damit die gespeicherte Form kein Carbon hält. */
        public readonly ?int $timestamp = null,
        public readonly ?string $messageId = null,
        public readonly string $text = '',
        public readonly bool $textFromHtml = false,
        public readonly bool $textTruncated = false,
        public readonly bool $hasHtml = false,
        /** Nur ein RTF-Körper (Outlook), den der Leser nicht wandelt. */
        public readonly bool $hasRtf = false,
        /** @var list<MailFilePart> */
        public readonly array $attachments = [],
        /** Original-HTML — nie in der gespeicherten Zusammenfassung ({@see toArray()}). */
        public readonly ?string $html = null,
        /** Die abgeschottete Originaldarstellung liegt vor (nicht zu groß, bereinigt). */
        public readonly bool $frameAvailable = false,
    ) {}

    /** Kopie mit gesetzter Verfügbarkeit der Originaldarstellung (Extraktion). */
    public function withFrame(bool $available): self {
        return new self($this->format, $this->subject, $this->from, $this->to, $this->cc, $this->bcc, $this->timestamp, $this->messageId,
            $this->text, $this->textFromHtml, $this->textTruncated, $this->hasHtml, $this->hasRtf, $this->attachments, $this->html, $available);
    }

    /** Anhang über seinen Pfad; ungültiger oder unbekannter Pfad → null. */
    public function part(string $path): ?MailFilePart {
        if (preg_match(self::PART_PATH_PATTERN, $path) !== 1) {
            return null;
        }

        $message = $this;
        $part = null;
        foreach (explode('.', $path) as $index) {
            if ($message === null) {
                return null;
            }
            $part = $message->attachments[(int) $index] ?? null;
            if ($part === null) {
                return null;
            }
            $message = $part->message;
        }

        return $part;
    }

    /** Die Mail selbst (leerer Pfad) oder eine eingebettete Mail über ihren Pfad. */
    public function messageAt(string $path): ?self {
        return $path === '' ? $this : $this->part($path)?->message;
    }

    /** Zeitpunkt der Mail (UTC) oder null; für die Anzeige über `fdatetime()`. */
    public function sentAt(): ?\Carbon\CarbonImmutable {
        return $this->timestamp !== null ? \Carbon\CarbonImmutable::createFromTimestampUTC($this->timestamp) : null;
    }

    /**
     * „Name <adresse>“ bzw. nur eine der beiden Angaben.
     *
     * @param  array{name?: string, address?: string}|null  $address
     */
    public static function addressLine(?array $address): string {
        if ($address === null) {
            return '';
        }
        $name = trim((string) ($address['name'] ?? ''));
        $mail = trim((string) ($address['address'] ?? ''));

        return match (true) {
            $name !== '' && $mail !== '' && $name !== $mail => $name . ' <' . $mail . '>',
            $mail !== '' => $mail,
            default => $name,
        };
    }

    /**
     * @param  list<array{name: string, address: string}>  $addresses
     */
    public static function addressList(array $addresses): string {
        return implode(', ', array_filter(array_map(self::addressLine(...), $addresses), static fn (string $line): bool => $line !== ''));
    }

    /** @return array<string, mixed> Gespeicherte Form ohne HTML und ohne Anhangsinhalte (nur Arrays und Skalare) */
    public function toArray(): array {
        return [
            'format' => $this->format->value,
            'subject' => $this->subject,
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'timestamp' => $this->timestamp,
            'message_id' => $this->messageId,
            'text' => $this->text,
            'text_from_html' => $this->textFromHtml,
            'text_truncated' => $this->textTruncated,
            'has_html' => $this->hasHtml,
            'has_rtf' => $this->hasRtf,
            'frame' => $this->frameAvailable,
            'attachments' => array_map(static fn (MailFilePart $part): array => $part->toArray(), $this->attachments),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        $address = static function (mixed $value): ?array {
            if (! is_array($value)) {
                return null;
            }

            return ['name' => (string) ($value['name'] ?? ''), 'address' => (string) ($value['address'] ?? '')];
        };
        $addresses = static fn (mixed $value): array => is_array($value)
            ? array_values(array_filter(array_map($address, $value)))
            : [];
        $optional = static fn (string $key): ?string => isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '' ? $data[$key] : null;

        return new self(
            format: MailFileFormat::tryFrom((string) ($data['format'] ?? '')) ?? MailFileFormat::Eml,
            subject: (string) ($data['subject'] ?? ''),
            from: $address($data['from'] ?? null),
            to: $addresses($data['to'] ?? null),
            cc: $addresses($data['cc'] ?? null),
            bcc: $addresses($data['bcc'] ?? null),
            timestamp: is_int($data['timestamp'] ?? null) ? $data['timestamp'] : null,
            messageId: $optional('message_id'),
            text: (string) ($data['text'] ?? ''),
            textFromHtml: (bool) ($data['text_from_html'] ?? false),
            textTruncated: (bool) ($data['text_truncated'] ?? false),
            hasHtml: (bool) ($data['has_html'] ?? false),
            hasRtf: (bool) ($data['has_rtf'] ?? false),
            attachments: is_array($data['attachments'] ?? null)
                ? array_values(array_map(static fn (mixed $part): MailFilePart => MailFilePart::fromArray(is_array($part) ? $part : []), $data['attachments']))
                : [],
            frameAvailable: (bool) ($data['frame'] ?? false),
        );
    }
}
