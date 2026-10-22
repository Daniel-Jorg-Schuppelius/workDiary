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

use App\Exceptions\MailboxFolderMissingException;
use App\Models\Mail\EmailConnection;
use CommonToolkit\Helper\Data\{EmailHelper, StringHelper};
use Generator;
use Illuminate\Support\Carbon;
use Throwable;
use Webklex\PHPIMAP\{Client, ClientManager, Folder, IMAP, Message};
use Webklex\PHPIMAP\Exceptions\{MessageHeaderFetchingException, MessageNotFoundException};

/**
 * IMAP-Transport über `webklex/php-imap` (Feature 056, MVP-117) — reine
 * PHP-Implementierung, kein `ext-imap` nötig. Abgerufen wird nach Stand
 * (MVP-1139): UIDVALIDITY plus höchste verarbeitete UID, im ersten Durchgang
 * ab `SINCE` des Startpunkts; der Lesestatus spielt keine Rolle. Verarbeitete
 * Nachrichten werden als gelesen gekennzeichnet bzw. verschoben (nie gelöscht).
 */
class ImapMailboxGateway implements MailboxGateway {
    /** @var array<int, Client> verbundene Clients je Postfach während eines Abrufs */
    private array $clients = [];

    /** @var array<int, list<Folder>> Ordnerliste je Client, einmal je Abruf geladen */
    private array $folderLists = [];

    public function fetch(EmailConnection $connection, MailboxCursor $cursor, int $limit): Generator {
        $client = $this->connect($connection);
        $this->clients[(int) $connection->id] = $client;

        try {
            $folder = $this->resolveFolder($client, (string) $connection->folder)
                ?? throw new MailboxFolderMissingException((string) $connection->folder);

            $uidValidity = (int) ($folder->examine()['uidvalidity'] ?? 0);
            if ($cursor->uidValidity !== null && $cursor->uidValidity !== $uidValidity) {
                $cursor = $cursor->restarted($connection);
            }
            $cursor = $cursor->with(['uidValidity' => $uidValidity]);

            $uids = $this->pendingUids($client, $folder, $cursor, $connection);
            foreach (array_slice($uids, 0, max(1, $limit)) as $uid) {
                try {
                    $message = $folder->query()->leaveUnread()->getMessageByUid($uid);
                } catch (MessageHeaderFetchingException|MessageNotFoundException) {
                    $message = null; // zwischen Suche und Abruf gelöscht
                }
                $cursor = $cursor->with(['lastUid' => $uid, 'resumeFrom' => null]);
                if ($message !== null) {
                    yield $this->parse($message)->withCursorAfter($cursor);
                }
            }

            return count($uids) <= $limit ? $cursor->with(['catchingUp' => false]) : $cursor;
        } finally {
            unset($this->clients[(int) $connection->id], $this->folderLists[spl_object_id($client)]);
            $this->disconnect($client);
        }
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        $client = $this->clients[(int) $connection->id] ?? null;
        $own = $client === null;

        try {
            $client ??= $this->connect($connection);
            $folder = $this->resolveFolder($client, (string) $connection->folder);
            if ($folder === null) {
                return;
            }
            $target = null;
            $processedFolder = trim((string) $connection->processed_folder);
            if ($processedFolder !== '') {
                $target = $this->resolveFolder($client, $processedFolder)
                    ?? throw new MailboxFolderMissingException($processedFolder);
            }

            $live = $folder->query()->leaveUnread()->getMessageByUid($message->uid);
            $live->setFlag('Seen');
            if ($target !== null) {
                $live->move($target->path, false, true);
            }
        } catch (MailboxFolderMissingException $e) {
            throw $e;
        } catch (Throwable) {
            // Best effort: der Stand verhindert ohnehin eine erneute Verarbeitung.
        } finally {
            if ($own && $client !== null) {
                unset($this->folderLists[spl_object_id($client)]);
                $this->disconnect($client);
            }
        }
    }

    public function find(EmailConnection $connection, string $messageId): ?ParsedMessage {
        $bare = trim($messageId, "<> \t");
        if ($bare === '') {
            return null;
        }
        $client = $this->connect($connection);

        try {
            $paths = array_values(array_unique(array_filter([(string) $connection->folder, trim((string) $connection->processed_folder)])));
            foreach ($paths as $path) {
                $folder = $this->resolveFolder($client, $path);
                if ($folder === null) {
                    continue;
                }
                // HEADER-Suche trifft die ID mit und ohne Klammern.
                $message = $folder->query()->leaveUnread()->whereMessageId($bare)->limit(1)->get()->first();
                if ($message instanceof Message) {
                    return $this->parse($message);
                }
            }

            return null;
        } finally {
            unset($this->folderLists[spl_object_id($client)]);
            $this->disconnect($client);
        }
    }

    public function folders(EmailConnection $connection): array {
        $client = $this->connect($connection);

        try {
            $paths = [];
            foreach ($client->getFolders(false, null, true) as $folder) {
                if ($folder instanceof Folder && ! $folder->no_select) {
                    $paths[] = self::displayPath($folder);
                }
            }
            sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

            return array_values(array_unique($paths));
        } finally {
            $this->disconnect($client);
        }
    }

    /**
     * UIDs nach dem Stand, aufsteigend. Rohsuche, weil die Bibliothek
     * Kriterienwerte in Anführungszeichen setzt und ein UID-Bereich so
     * ungültig wäre; `n:*` liefert laut IMAP immer die letzte Mail mit, auch
     * wenn ihre UID kleiner als n ist.
     *
     * @return list<int>
     */
    private function pendingUids(Client $client, Folder $folder, MailboxCursor $cursor, EmailConnection $connection): array {
        $criteria = [];
        if ($cursor->lastUid !== null) {
            $criteria[] = 'UID ' . ($cursor->lastUid + 1) . ':*';
        }
        $since = $cursor->since($connection);
        if ($since !== null) {
            $criteria[] = 'SINCE ' . $since->format('d-M-Y');
        }

        $client->openFolder($folder->path);
        $found = $client->getConnection()->search([$criteria !== [] ? implode(' ', $criteria) : 'ALL'], IMAP::ST_UID)->validatedData();

        $uids = array_values(array_filter(
            array_map('intval', (array) $found),
            static fn (int $uid): bool => $uid > 0 && ($cursor->lastUid === null || $uid > $cursor->lastUid),
        ));
        sort($uids);

        return $uids;
    }

    /**
     * Ordner per Pfad mit `/`, per Serverpfad (z. B. `INBOX.Rechnungen`) oder
     * per Blattname in beliebiger Tiefe (erster Treffer); `INBOX` ohne
     * Rücksicht auf die Schreibweise.
     */
    private function resolveFolder(Client $client, string $wanted): ?Folder {
        $wanted = trim($wanted, " /\t");
        if ($wanted === '') {
            $wanted = 'INBOX';
        }
        $folders = $this->folderLists[spl_object_id($client)] ??= array_values(array_filter(
            iterator_to_array($client->getFolders(false, null, true)),
            static fn (mixed $folder): bool => $folder instanceof Folder,
        ));

        $normalize = static fn (string $path): string => preg_replace('/^inbox(?=\/|$)/i', 'INBOX', $path) ?? $path;
        $target = $normalize($wanted);
        foreach ($folders as $folder) {
            if ($normalize(self::displayPath($folder)) === $target || $normalize($folder->full_name) === $target || $folder->path === $wanted) {
                return $folder;
            }
        }
        if (! str_contains($wanted, '/')) {
            foreach ($folders as $folder) {
                if ($folder->name === $wanted) {
                    return $folder;
                }
            }
        }

        return null;
    }

    private static function displayPath(Folder $folder): string {
        $delimiter = (string) $folder->delimiter;

        return $delimiter !== '' && $delimiter !== '/' ? str_replace($delimiter, '/', $folder->full_name) : $folder->full_name;
    }

    private function connect(EmailConnection $connection): Client {
        $client = (new ClientManager())->make([
            'host' => $connection->host,
            'port' => $connection->port,
            'encryption' => $connection->encryption === 'none' ? false : $connection->encryption,
            'validate_cert' => true,
            'username' => $connection->username,
            'password' => $connection->password,
            'protocol' => 'imap',
        ]);
        $client->connect();

        return $client;
    }

    private function disconnect(Client $client): void {
        try {
            $client->disconnect();
        } catch (Throwable) {
            // Verbindung ist ohnehin weg.
        }
    }

    private function parse(Message $message): ParsedMessage {
        [$email, $name] = $this->splitAddress((string) $message->getFrom());

        $body = $message->getTextBody();
        if (trim($body) === '') {
            $body = StringHelper::htmlToText($message->getHTMLBody());
        }

        $messageId = trim((string) $message->getMessageId());
        $uid = (int) $message->getUid();
        if ($messageId === '') {
            $messageId = 'uid-' . $uid . '@' . $message->getFolder()?->path;
        }

        // Threading + Loop-Schutz (Feature 065, P2) — Header defensiv lesen.
        $inReplyTo = trim((string) $message->getInReplyTo());
        $references = StringHelper::words((string) $message->getReferences());
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
            attachments: $this->extractAttachments($message, true),
            inReplyTo: $inReplyTo !== '' ? $inReplyTo : null,
            references: $references,
            isAutoSubmitted: $autoSubmitted,
        );
    }

    /**
     * Anhänge als transport-neutrale {@see MailAttachment} (Rang 7); die
     * Whitelist-/Größenprüfung passiert erst im Intake. Eine angehängte Mail
     * (`message/rfc822`, „Als Anlage weiterleiten“) wird eine Ebene tief
     * ausgepackt (MVP-1142), sonst ginge die Rechnung darin verloren.
     *
     * @return list<MailAttachment>
     */
    private function extractAttachments(Message $message, bool $unpackMessages): array {
        $out = [];
        foreach ($message->getAttachments() as $attachment) {
            $content = (string) $attachment->getContent();
            if ($content === '') {
                continue;
            }
            $isMessage = $attachment->type === 'message' || strtolower((string) $attachment->content_type) === 'message/rfc822';
            if ($isMessage && $unpackMessages) {
                try {
                    array_push($out, ...$this->extractAttachments(Message::fromString($content), false));

                    continue;
                } catch (Throwable) {
                    // Nicht lesbar → als Datei behalten.
                }
            }
            $out[] = new MailAttachment(
                filename: (string) ($attachment->getName() ?: 'anhang'),
                mime: (string) ($attachment->getMimeType() ?: 'application/octet-stream'),
                content: $content,
                isInline: strtolower((string) $attachment->getDisposition()) === 'inline',
            );
        }

        return $out;
    }

    /** @return array{0: string, 1: string} [email, name] */
    private function splitAddress(string $raw): array {
        $raw = trim($raw);
        if (preg_match('/^(.*)<([^>]+)>\s*$/', $raw, $m) === 1) {
            return [EmailHelper::normalize($m[2]), trim($m[1], " \"'")];
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
