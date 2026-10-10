<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTransferResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Dto;

use App\Enums\Invoicing\IncomingInvoiceTransferStatus;

/** Ergebnis einer Übergabe an ein Buchhaltungsziel (Feature 163, MVP-1111). */
final class IncomingInvoiceTransferResult {
    private function __construct(
        public readonly IncomingInvoiceTransferStatus $status,
        public readonly ?string $externalId = null,
        public readonly ?string $externalNumber = null,
        public readonly ?string $note = null,
    ) {}

    public static function transferred(string $externalId, ?string $externalNumber = null, ?string $note = null): self {
        return new self(IncomingInvoiceTransferStatus::Transferred, $externalId, $externalNumber, $note);
    }

    /** Im Zielsystem schon vorhanden: nur verknüpft, nichts hochgeladen. */
    public static function linked(string $externalId, ?string $externalNumber = null): self {
        return new self(IncomingInvoiceTransferStatus::Linked, $externalId, $externalNumber);
    }

    /** Ein Mensch muss etwas klären; ein späterer Lauf versucht es erneut. */
    public static function waiting(string $note): self {
        return new self(IncomingInvoiceTransferStatus::Waiting, note: $note);
    }
}
