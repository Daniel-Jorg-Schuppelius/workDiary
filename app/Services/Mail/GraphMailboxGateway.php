<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GraphMailboxGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\{EmailConnection, MsgraphMailConnection};
use App\Plugins\Msgraph\Api\MsgraphMailClient;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Microsoft-Graph-Transport hinter {@see MailboxGateway} (Feature 102,
 * MS365-Plan B): Exchange Online hat IMAP-Basic-Auth 2023 abgeschaltet —
 * M365-Postfächer laufen stattdessen über die Graph-Mail-Verbindung der
 * Organisation (delegated; für den Eingang ist der Scope `Mail.ReadWrite`
 * in MSGRAPH_MAIL_SCOPES nötig). Semantik wie der IMAP-Adapter: ungelesene
 * Nachrichten abrufen OHNE sie zu markieren; verarbeitete als gelesen
 * kennzeichnen bzw. verschieben — nie löschen. Der gesamte
 * {@see MailIntakeService}-Downstream bleibt unverändert.
 */
class GraphMailboxGateway implements MailboxGateway {
    public function fetch(EmailConnection $connection): array {
        $client = $this->client($connection);
        if ($client === null) {
            return [];
        }

        $out = [];
        foreach ($client->listUnreadMessages($this->graphFolder($connection)) as $message) {
            $out[] = $this->parse($client, $message);
        }

        return $out;
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        if ($message->externalId === null || $message->externalId === '') {
            return;
        }

        try {
            $client = $this->client($connection);
            if ($client === null) {
                return;
            }
            $client->markRead($message->externalId);
            if ($connection->processed_folder !== null && $connection->processed_folder !== '') {
                $client->moveToFolder($message->externalId, $connection->processed_folder);
            }
        } catch (Throwable) {
            // Best effort — der Dublettenschutz über die Message-ID verhindert
            // ein erneutes Anlegen beim nächsten Abruf (IMAP-Muster).
        }
    }

    /** Graph-Mail-Verbindung der Organisation (null = nicht verbunden). */
    private function client(EmailConnection $connection): ?MsgraphMailClient {
        $mail = MsgraphMailConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->first();
        if (! $mail instanceof MsgraphMailConnection || ! $mail->isActive()) {
            return null;
        }

        return new MsgraphMailClient($mail);
    }

    /** Ordner-Feld der Verbindung → Graph-Well-Known-Name (INBOX-Konvention des IMAP-Formulars). */
    private function graphFolder(EmailConnection $connection): string {
        $folder = trim((string) $connection->folder);

        return $folder === '' || strcasecmp($folder, 'INBOX') === 0 ? 'inbox' : $folder;
    }

    /** @param array<string, mixed> $message */
    private function parse(MsgraphMailClient $client, array $message): ParsedMessage {
        $graphId = (string) ($message['id'] ?? '');

        $from = (array) ($message['from'] ?? []);
        $fromAddress = (array) ($from['emailAddress'] ?? []);
        $email = strtolower(trim((string) ($fromAddress['address'] ?? '')));
        $name = trim((string) ($fromAddress['name'] ?? ''));

        $body = (array) ($message['body'] ?? []);
        $content = (string) ($body['content'] ?? '');
        if (strcasecmp((string) ($body['contentType'] ?? ''), 'html') === 0) {
            $content = trim(strip_tags($content));
        }

        // Threading + Loop-Schutz aus den Internet-Headern (IMAP-Parität).
        $inReplyTo = '';
        $references = [];
        $autoSubmitted = false;
        foreach ((array) ($message['internetMessageHeaders'] ?? []) as $header) {
            $headerName = strtolower((string) (is_array($header) ? ($header['name'] ?? '') : ''));
            $value = trim((string) (is_array($header) ? ($header['value'] ?? '') : ''));
            if ($headerName === 'in-reply-to') {
                $inReplyTo = $value;
            } elseif ($headerName === 'references') {
                $references = array_values(array_filter(preg_split('/\s+/', $value) ?: []));
            } elseif ($headerName === 'auto-submitted' && $value !== '' && strcasecmp($value, 'no') !== 0) {
                $autoSubmitted = true;
            } elseif ($headerName === 'x-auto-response-suppress' && $value !== '') {
                $autoSubmitted = true;
            }
        }

        $attachments = [];
        if ((bool) ($message['hasAttachments'] ?? false)) {
            foreach ($client->messageAttachments($graphId) as $attachment) {
                $raw = base64_decode($attachment['contentBytes'], true);
                if (! is_string($raw) || $raw === '') {
                    continue;
                }
                $attachments[] = new MailAttachment(
                    filename: $attachment['name'],
                    mime: $attachment['contentType'],
                    content: $raw,
                );
            }
        }

        $messageId = trim((string) ($message['internetMessageId'] ?? ''));
        if ($messageId === '') {
            $messageId = 'graph-' . $graphId;
        }

        try {
            $receivedAt = Carbon::parse((string) ($message['receivedDateTime'] ?? ''));
        } catch (Throwable) {
            $receivedAt = Carbon::now();
        }

        return new ParsedMessage(
            messageId: $messageId,
            uid: 0, // Graph adressiert über externalId, nicht über IMAP-UIDs
            fromEmail: $email,
            fromName: $name,
            subject: (string) ($message['subject'] ?? ''),
            body: $content,
            receivedAt: $receivedAt,
            attachmentCount: count($attachments),
            attachments: $attachments,
            inReplyTo: $inReplyTo !== '' ? $inReplyTo : null,
            references: $references,
            isAutoSubmitted: $autoSubmitted,
            externalId: $graphId,
        );
    }
}
