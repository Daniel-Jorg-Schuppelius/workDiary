<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailboxGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Exceptions\{MailboxFolderMissingException, MailboxUnavailableException};
use App\Models\Mail\EmailConnection;
use App\Plugins\Msgraph\Api\MsgraphMailClient;
use App\Plugins\Msgraph\Models\MsgraphMailConnection;
use App\Services\CloudIntake\StaleCheckpointException;
use App\Services\Mail\{MailAttachment, MailboxCursor, MailboxGateway, ParsedMessage};
use CommonToolkit\Helper\Data\{EmailHelper, StringHelper};
use Generator;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Microsoft-Graph-Transport hinter {@see MailboxGateway} (Feature 102,
 * MS365-Plan B): Exchange Online hat IMAP-Basic-Auth 2023 abgeschaltet —
 * M365-Postfächer laufen über die Graph-Mail-Verbindung der Organisation
 * (delegiert). Abgerufen wird nach Stand über die Delta-Abfrage des Ordners
 * (MVP-1139), der Ordner per Pfad (MVP-1140), auf Wunsch aus einem
 * freigegebenen Postfach (MVP-1141). Verarbeitete Nachrichten werden als
 * gelesen gekennzeichnet bzw. verschoben — nie gelöscht.
 */
class MsgraphMailboxGateway implements MailboxGateway {
    /** @var array<string, string> aufgelöste Verarbeitet-Ordner je Postfach und Pfad */
    private array $processedFolders = [];

    public function find(EmailConnection $connection, string $messageId): ?ParsedMessage {
        $bare = trim($messageId, "<> \t");
        if ($bare === '') {
            return null;
        }
        $client = $this->client($connection);
        $mailbox = $connection->mailbox_address;
        $raw = $client->findByInternetMessageId($mailbox, '<' . $bare . '>');

        return $raw === null ? null : $this->parse($client, $mailbox, $raw);
    }

    public function fetch(EmailConnection $connection, MailboxCursor $cursor, int $limit): Generator {
        $client = $this->client($connection);
        $mailbox = $connection->mailbox_address;

        $folderId = $cursor->folderId ?? $client->folderId($mailbox, (string) $connection->folder)
            ?? throw new MailboxFolderMissingException((string) $connection->folder);
        $cursor = $cursor->with(['folderId' => $folderId]);

        $remaining = max(1, $limit);
        while ($remaining > 0) {
            try {
                $page = $client->messagesDelta($mailbox, $folderId, $cursor->link, $cursor->link === null ? $cursor->since($connection) : null, $remaining);
            } catch (StaleCheckpointException $e) {
                if ($cursor->link === null) {
                    throw $e;
                }
                $cursor = $cursor->restarted($connection);

                continue;
            }

            $next = $cursor->with([
                'link' => $page['next'] ?? $page['delta'],
                'resumeFrom' => $page['next'] !== null ? $cursor->resumeFrom : null,
                'catchingUp' => $page['next'] !== null ? $cursor->catchingUp : false,
            ]);
            $last = array_key_last($page['ids']);
            foreach ($page['ids'] as $index => $id) {
                $raw = $client->message($mailbox, $id);
                if ($raw !== null) {
                    $parsed = $this->parse($client, $mailbox, $raw);

                    yield $index === $last ? $parsed->withCursorAfter($next) : $parsed;
                }
            }
            $cursor = $next;
            if ($page['next'] === null) {
                break;
            }
            $remaining -= max(1, count($page['ids']));
        }

        return $cursor;
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        if ($message->externalId === null || $message->externalId === '') {
            return;
        }
        $client = $this->client($connection);
        $mailbox = $connection->mailbox_address;

        $target = null;
        $processedFolder = trim((string) $connection->processed_folder);
        if ($processedFolder !== '') {
            $key = $connection->id . '|' . $processedFolder;
            $target = $this->processedFolders[$key] ??= ($client->folderId($mailbox, $processedFolder)
                ?? throw new MailboxFolderMissingException($processedFolder));
        }

        try {
            $client->markRead($mailbox, $message->externalId);
            if ($target !== null) {
                $client->moveToFolder($mailbox, $message->externalId, $target);
            }
        } catch (Throwable) {
            // Best effort — der Stand verhindert ohnehin eine erneute Verarbeitung.
        }
    }

    public function folders(EmailConnection $connection): array {
        return $this->client($connection)->folderPaths($connection->mailbox_address);
    }

    /**
     * Graph-Mail-Verbindung der Organisation. Fehlt sie oder das nötige Recht,
     * steht die Meldung am Postfach (MVP-1141) statt eines HTTP-Fehlers.
     */
    private function client(EmailConnection $connection): MsgraphMailClient {
        $mail = MsgraphMailConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->first();
        if (! $mail instanceof MsgraphMailConnection || ! $mail->isActive()) {
            throw new MailboxUnavailableException((string) __('msgraph::mail.flash.msgraph_connection_required'));
        }
        $missing = MsgraphMailScopes::missingFor($mail, $connection);
        if ($missing !== null) {
            throw new MailboxUnavailableException((string) __('msgraph::mail.error.scope_missing', ['scope' => $missing]));
        }

        return new MsgraphMailClient($mail);
    }

    /** @param array<string, mixed> $message */
    private function parse(MsgraphMailClient $client, ?string $mailbox, array $message): ParsedMessage {
        $graphId = (string) ($message['id'] ?? '');

        $from = (array) ($message['from'] ?? []);
        $fromAddress = (array) ($from['emailAddress'] ?? []);
        $email = EmailHelper::normalize((string) ($fromAddress['address'] ?? ''));
        $name = trim((string) ($fromAddress['name'] ?? ''));

        $body = (array) ($message['body'] ?? []);
        $content = (string) ($body['content'] ?? '');
        if (strcasecmp((string) ($body['contentType'] ?? ''), 'html') === 0) {
            $content = StringHelper::htmlToText($content);
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
            foreach ($client->messageAttachments($mailbox, $graphId) as $attachment) {
                $raw = base64_decode($attachment['contentBytes'], true);
                if (! is_string($raw) || $raw === '') {
                    continue;
                }
                $attachments[] = new MailAttachment(
                    filename: $attachment['name'],
                    mime: $attachment['contentType'],
                    content: $raw,
                    isInline: $attachment['isInline'],
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
