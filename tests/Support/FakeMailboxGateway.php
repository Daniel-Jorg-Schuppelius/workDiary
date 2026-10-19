<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeMailboxGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Exceptions\MailboxFolderMissingException;
use App\Models\Mail\EmailConnection;
use App\Services\Mail\{MailboxCursor, MailboxGateway, ParsedMessage};
use Generator;

/**
 * Test-Double für {@see MailboxGateway} (Feature 056): liefert voreingestellte
 * Nachrichten nach Stand wie ein IMAP-Postfach (UID > `lastUid`, höchstens
 * `$limit`) und protokolliert `markProcessed` — kein Server.
 */
class FakeMailboxGateway implements MailboxGateway {
    /** @var list<int> */
    public array $processedUids = [];

    /** @var list<MailboxCursor> */
    public array $cursors = [];

    /** Simuliert einen fehlenden Verarbeitet-Ordner. */
    public ?string $missingProcessedFolder = null;

    /** @var list<string> */
    public array $folderList = ['INBOX'];

    /** @param list<ParsedMessage> $messages */
    public function __construct(private array $messages = []) {}

    public function fetch(EmailConnection $connection, MailboxCursor $cursor, int $limit): Generator {
        $this->cursors[] = $cursor;
        $pending = array_values(array_filter($this->messages, static fn (ParsedMessage $m): bool => $cursor->lastUid === null || $m->uid > $cursor->lastUid));
        usort($pending, static fn (ParsedMessage $a, ParsedMessage $b): int => $a->uid <=> $b->uid);

        foreach (array_slice($pending, 0, $limit) as $message) {
            $cursor = $cursor->with(['lastUid' => $message->uid]);

            yield $message->withCursorAfter($cursor);
        }

        return count($pending) <= $limit ? $cursor->with(['catchingUp' => false]) : $cursor;
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        if ($this->missingProcessedFolder !== null) {
            throw new MailboxFolderMissingException($this->missingProcessedFolder);
        }
        $this->processedUids[] = $message->uid;
    }

    public function folders(EmailConnection $connection): array {
        return $this->folderList;
    }

    public function find(EmailConnection $connection, string $messageId): ?ParsedMessage {
        $bare = trim($messageId, "<> \t");
        foreach ($this->messages as $message) {
            if (trim($message->messageId, "<> \t") === $bare) {
                return $message;
            }
        }

        return null;
    }
}
