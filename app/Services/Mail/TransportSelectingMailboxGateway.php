<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransportSelectingMailboxGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Exceptions\MailboxUnavailableException;
use App\Models\Mail\EmailConnection;
use Generator;

/**
 * Transport-Weiche vor {@see MailboxGateway} (Feature 102): wählt je
 * Postfach den IMAP- oder Graph-Adapter (`email_connections.transport`).
 * Der Poll-/Intake-Kern kennt weiterhin nur das Interface.
 */
class TransportSelectingMailboxGateway implements MailboxGateway {
    public function __construct(
        private readonly ImapMailboxGateway $imap,
        private readonly MailboxTransports $transports,
    ) {}

    public function fetch(EmailConnection $connection, MailboxCursor $cursor, int $limit): Generator {
        return $this->gatewayFor($connection)->fetch($connection, $cursor, $limit);
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        $this->gatewayFor($connection)->markProcessed($connection, $message);
    }

    public function folders(EmailConnection $connection): array {
        return $this->gatewayFor($connection)->folders($connection);
    }

    public function find(EmailConnection $connection, string $messageId): ?ParsedMessage {
        return $this->gatewayFor($connection)->find($connection, $messageId);
    }

    private function gatewayFor(EmailConnection $connection): MailboxGateway {
        if ($connection->usesImap()) {
            return $this->imap;
        }

        return $this->transports->get((string) $connection->transport)?->gateway()
            ?? throw new MailboxUnavailableException('Postfach-Transport „' . $connection->transport . '“ ist nicht verfügbar.');
    }
}
