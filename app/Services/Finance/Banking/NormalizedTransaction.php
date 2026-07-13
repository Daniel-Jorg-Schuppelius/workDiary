<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NormalizedTransaction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Banking;

use App\Enums\Finance\TransactionDirection;

/**
 * Formatneutraler Bankumsatz (Feature 045, „Fachliches Transfermodell ·
 * Bankumsatz"). Entkoppelt die Toolkit-Entities von der Persistenz: der
 * BankStatementParser füllt dieses DTO, der BankImportService schreibt es.
 */
final class NormalizedTransaction {
    /** @param list<string> $extractedRefs */
    public function __construct(
        public readonly int $lineIndex,
        public readonly string $bookingDate,
        public readonly ?string $valutaDate,
        public readonly float $amount,
        public readonly TransactionDirection $direction,
        public readonly string $currency,
        public readonly ?string $endToEndId,
        public readonly ?string $mandateRef,
        public readonly ?string $counterpartyName,
        public readonly ?string $counterpartyIban,
        public readonly ?string $purpose,
        public readonly array $extractedRefs,
        public readonly bool $isReversal,
        /** ISO-20022-Rückgabegrund (RtrInf/Rsn/Cd, z. B. AC04) — MVP-334. */
        public readonly ?string $returnReason = null,
    ) {}
}
