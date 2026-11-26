<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingOrigin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

/** Herkunft eines Rechnungseingangs aus dem Postfach (MVP-1107): Absender und Message-ID; seit MVP-1811 auch Absendername, Empfangszeit und Postfach. */
final class IncomingOrigin {
    /** @param  list<string>  $attachmentNames */
    public function __construct(
        public readonly ?string $senderEmail = null,
        public readonly ?string $reference = null,
        public readonly ?string $subject = null,
        public readonly array $attachmentNames = [],
        public readonly ?string $senderName = null,
        public readonly ?string $receivedAt = null,
        public readonly ?string $mailbox = null,
        // Text der Mail (MVP-1812), zum Nachsehen neben dem Original.
        public readonly ?string $body = null,
    ) {}
}
