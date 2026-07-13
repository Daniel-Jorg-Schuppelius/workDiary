<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NormalizedTransactionDetail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Banking;

/**
 * Einzeltransaktion (TxDtls) innerhalb einer Sammelbuchung (Feature 045,
 * Toolkit-Folgepaket 2 zu MVP-334): formatneutrales Abbild eines
 * CAMT-TransactionDetail. Wird NUR gefüllt, wenn die Buchung mehrere TxDtls
 * trägt — Einzel-TxDtls-Buchungen bleiben unverändert (die Einzelwert-Felder
 * der {@see NormalizedTransaction} decken das erste TxDtls bereits ab).
 *
 * Der Betrag ist SIGNIERT aus Kontosicht (Haben +, Soll −), damit gemischte
 * Sammler (z. B. Sammellastschrift mit einzelnen Rückgaben) verlustfrei
 * abgebildet werden.
 */
final class NormalizedTransactionDetail {
    public function __construct(
        public readonly float $signedAmount,
        public readonly ?string $endToEndId,
        public readonly ?string $mandateRef,
        public readonly ?string $counterpartyName,
        public readonly ?string $counterpartyIban,
        public readonly ?string $purpose,
        /** ISO-20022-Rückgabegrund je Detail (RtrInf/Rsn/Cd, z. B. AC04). */
        public readonly ?string $returnReason,
    ) {}
}
