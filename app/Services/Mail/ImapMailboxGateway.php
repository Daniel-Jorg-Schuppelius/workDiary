<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImapMailboxGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\EmailConnection;
use Illuminate\Support\Carbon;
use Throwable;
use Webklex\PHPIMAP\{ClientManager, Message};

/**
 * IMAP-Transport über `webklex/php-imap` (Feature 056, MVP-117) — reine
 * PHP-Implementierung, kein `ext-imap` nötig. Ruft ungelesene Nachrichten ab
 * (ohne sie zu markieren) und kennzeichnet verarbeitete Nachrichten als gelesen
 * bzw. verschiebt sie (nie löschen). Dieser Adapter ist die austauschbare
 * Transportschicht hinter {@see MailboxGateway}; der Intake-Kern hängt nicht an
 * IMAP.
 */
class ImapMailboxGateway implements MailboxGateway {
    public function fetch(EmailConnection $connection): array {
        $client = $this->client($connection);
        $client->connect();

        $folder = $client->getFolder($connection->folder);
        if ($folder === null) {
            return [];
        }

        $messages = $folder->query()->unseen()->leaveUnread()->limit(100)->get();

        $out = [];
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $out[] = $this->parse($message);
            }
        }

        return $out;
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        try {
            $client = $this->client($connection);
            $client->connect();
            $folder = $client->getFolder($connection->folder);
            if ($folder === null) {
                return;
            }
            $live = $folder->query()->getMessageByUid($message->uid);
            $live->setFlag('Seen');
            if ($connection->processed_folder !== null && $connection->processed_folder !== '') {
                $live->move($connection->processed_folder);
            }
        } catch (Throwable) {
            // Best effort: der Dublettenschutz über die Message-ID verhindert
            // ohnehin ein erneutes Anlegen beim nächsten Abruf.
        }
    }

    private function client(EmailConnection $connection): \Webklex\PHPIMAP\Client {
        return (new ClientManager())->make([
            'host' => $connection->host,
            'port' => $connection->port,
            'encryption' => $connection->encryption === 'none' ? false : $connection->encryption,
            'validate_cert' => true,
            'username' => $connection->username,
            'password' => $connection->password,
            'protocol' => 'imap',
        ]);
    }

    private function parse(Message $message): ParsedMessage {
        [$email, $name] = $this->splitAddress((string) $message->getFrom());

        $body = $message->getTextBody();
        if (trim($body) === '') {
            $body = trim(strip_tags($message->getHTMLBody()));
        }

        $messageId = trim((string) $message->getMessageId());
        $uid = (int) $message->getUid();
        if ($messageId === '') {
            $messageId = 'uid-' . $uid . '@' . $message->getFolder()?->path;
        }

        // Threading + Loop-Schutz (Feature 065, P2) — Header defensiv lesen.
        $inReplyTo = trim((string) $message->getInReplyTo());
        $references = array_values(array_filter(preg_split('/\s+/', trim((string) $message->getReferences())) ?: []));
        $autoSubmitted = false;
        try {
            $headers = $message->getHeader();
            $autoValue = strtolower(trim((string) $headers?->get('auto_submitted')));
            $suppress = trim((string) $headers?->get('x_auto_response_suppress'));
            $autoSubmitted = ($autoValue !== '' && $autoValue !== 'no') || $suppress !== '';
        } catch (Throwable) {
            // Header fehlen → keine Auto-Reply-Markierung.
        }

        return new ParsedMessage(
            messageId: $messageId,
            uid: $uid,
            fromEmail: $email,
            fromName: $name,
            subject: (string) $message->getSubject(),
            body: $body,
            receivedAt: $this->parseDate((string) $message->getDate()),
            attachmentCount: $message->getAttachments()->count(),
            attachments: $this->extractAttachments($message),
            inReplyTo: $inReplyTo !== '' ? $inReplyTo : null,
            references: $references,
            isAutoSubmitted: $autoSubmitted,
        );
    }

    /**
     * Extrahiert Anhänge als transport-neutrale {@see MailAttachment} (Rang 7).
     * Die Whitelist-/Größenprüfung passiert erst im Intake — hier nur einlesen.
     *
     * @return list<MailAttachment>
     */
    private function extractAttachments(Message $message): array {
        $out = [];
        foreach ($message->getAttachments() as $attachment) {
            $content = (string) $attachment->getContent();
            if ($content === '') {
                continue;
            }
            $out[] = new MailAttachment(
                filename: (string) ($attachment->getName() ?: 'anhang'),
                mime: (string) ($attachment->getMimeType() ?: 'application/octet-stream'),
                content: $content,
            );
        }

        return $out;
    }

    /** @return array{0: string, 1: string} [email, name] */
    private function splitAddress(string $raw): array {
        $raw = trim($raw);
        if (preg_match('/^(.*)<([^>]+)>\s*$/', $raw, $m) === 1) {
            return [strtolower(trim($m[2])), trim($m[1], " \"'")];
        }

        return [strtolower($raw), ''];
    }

    private function parseDate(string $raw): Carbon {
        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return Carbon::now();
        }
    }
}
