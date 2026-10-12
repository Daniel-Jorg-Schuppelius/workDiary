<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsedMessage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Mail\EmailConnection;
use Illuminate\Support\{Carbon, Str};

/**
 * Transport-neutrale, bereits geparste Eingangsnachricht (Feature 056, MVP-117).
 * Der IMAP-Adapter erzeugt sie; der {@see MailIntakeService} arbeitet nur hiermit
 * (im Test synthetisch, kein echter Server).
 */
final class ParsedMessage {
    /**
     * @param  int  $attachmentCount  Gemeldete Anzahl Anhänge (auch wenn der
     *                                Inhalt nicht extrahiert wurde)
     * @param  list<MailAttachment>  $attachments  Extrahierte Anhänge (Rang 7):
     *                                der Intake persistiert sie temporär und die
     *                                Auflösung übernimmt sie an die Notiz/ins DMS
     * @param  list<string>  $references  Message-IDs der References-Kette (Threading, 065)
     */
    public function __construct(
        public readonly string $messageId,
        public readonly int $uid,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly string $subject,
        public readonly string $body,
        public readonly Carbon $receivedAt,
        public readonly int $attachmentCount = 0,
        public readonly array $attachments = [],
        // Threading + Loop-Schutz (Feature 065, P2):
        public readonly ?string $inReplyTo = null,
        public readonly array $references = [],
        public readonly bool $isAutoSubmitted = false,
        // Provider-ID für Nicht-IMAP-Transporte (Graph-Message-ID, Feature 102);
        // IMAP adressiert weiterhin über die numerische UID.
        public readonly ?string $externalId = null,
        // Abrufstand nach dieser Nachricht (MVP-1139); null = noch kein Sicherungspunkt.
        public readonly ?MailboxCursor $cursorAfter = null,
    ) {}

    public function withCursorAfter(?MailboxCursor $cursor): self {
        return $this->copy(['cursorAfter' => $cursor]);
    }

    /** @param list<MailAttachment> $attachments */
    public function withAttachments(array $attachments): self {
        return $this->copy(['attachments' => $attachments]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self {
        $pick = fn (string $key): mixed => array_key_exists($key, $changes) ? $changes[$key] : $this->{$key};

        return new self(
            messageId: $this->messageId,
            uid: $this->uid,
            fromEmail: $this->fromEmail,
            fromName: $this->fromName,
            subject: $this->subject,
            body: $this->body,
            receivedAt: $this->receivedAt,
            attachmentCount: $this->attachmentCount,
            attachments: $pick('attachments'),
            inReplyTo: $this->inReplyTo,
            references: $this->references,
            isAutoSubmitted: $this->isAutoSubmitted,
            externalId: $this->externalId,
            cursorAfter: $pick('cursorAfter'),
        );
    }

    /**
     * Roh-/Herkunftsnachweis für das Inbox-Item (Postfach + Message-ID, DoD 056).
     *
     * @return array<string, mixed>
     */
    public function snapshot(EmailConnection $connection): array {
        return [
            'message_id' => $this->messageId,
            'from' => ['email' => $this->fromEmail, 'name' => $this->fromName],
            'subject' => $this->subject,
            'body' => Str::limit($this->body, 20000),
            'received_at' => $this->receivedAt->toIso8601String(),
            'attachment_count' => $this->attachments !== [] ? count($this->attachments) : $this->attachmentCount,
            'mailbox' => $connection->name . ' / ' . $connection->folder,
        ];
    }
}
