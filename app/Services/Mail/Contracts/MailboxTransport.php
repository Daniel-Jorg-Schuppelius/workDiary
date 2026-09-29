<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxTransport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail\Contracts;

use App\Models\Platform\Organization;
use App\Services\Mail\MailboxGateway;

/**
 * Postfach-Transport neben IMAP (MVP-1042), z. B. Microsoft Graph. Der Wert
 * `key()` steht in `email_connections.transport`; Plugins tragen sich in
 * {@see \App\Services\Mail\MailboxTransports} ein.
 */
interface MailboxTransport {
    public function key(): string;

    public function label(): string;

    public function hint(): ?string;

    public function gateway(): MailboxGateway;

    /** Meldung, solange der Transport in der Organisation nicht nutzbar ist (null = bereit). */
    public function unavailableReason(Organization $organization): ?string;
}
