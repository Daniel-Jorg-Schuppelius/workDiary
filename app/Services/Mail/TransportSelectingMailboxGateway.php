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

use App\Models\EmailConnection;

/**
 * Transport-Weiche vor {@see MailboxGateway} (Feature 102): wählt je
 * Postfach den IMAP- oder Graph-Adapter (`email_connections.transport`).
 * Der Poll-/Intake-Kern kennt weiterhin nur das Interface.
 */
class TransportSelectingMailboxGateway implements MailboxGateway {
    public function __construct(
        private readonly ImapMailboxGateway $imap,
        private readonly GraphMailboxGateway $graph,
    ) {}

    public function fetch(EmailConnection $connection): array {
        return $this->gatewayFor($connection)->fetch($connection);
    }

    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {
        $this->gatewayFor($connection)->markProcessed($connection, $message);
    }

    private function gatewayFor(EmailConnection $connection): MailboxGateway {
        return $connection->isMsgraph() ? $this->graph : $this->imap;
    }
}
