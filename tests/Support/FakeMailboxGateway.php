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

use App\Models\EmailConnection;
use App\Services\Mail\{MailboxGateway, ParsedMessage};

/**
 * Test-Double für {@see MailboxGateway} (Feature 056): liefert voreingestellte
 * Nachrichten und protokolliert `markProcessed`-Aufrufe — kein IMAP.
 */
class FakeMailboxGateway implements MailboxGateway {
    /** @var list<int> */
    public array $processedUids = [];

    /** @param list<ParsedMessage> $messages */
    public function __construct(private array $messages = []) {}

    public function fetch(EmailConnection $connection): array {
        return $this->messages;
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        $this->processedUids[] = $message->uid;
    }
}
