<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankStatementParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Banking;

use App\Enums\Finance\{BankStatementFormat, TransactionDirection};
use App\Services\Finance\{BankImportException, FinancialFormatsSupport};
use CommonToolkit\FinancialFormats\Entities\ISO20022\Camt\Type53\{Document as Camt053Document, Transaction as Camt053Transaction};
use CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\Type1\Document as Pain001Document;
use CommonToolkit\FinancialFormats\Entities\ISO20022\Pain\Type8\Document as Pain008Document;
use CommonToolkit\FinancialFormats\Entities\OFX\{Statement as OfxStatement, Transaction as OfxTransaction};
use CommonToolkit\FinancialFormats\Entities\QIF\Transaction as QifTransaction;
use CommonToolkit\FinancialFormats\Entities\QXF\Transaction as QxfTransaction;
use CommonToolkit\FinancialFormats\Entities\Swift\Mt9xx\Purpose as Mt940Purpose;
use CommonToolkit\FinancialFormats\Entities\Swift\Mt9xx\Type940\{Document as Mt940Document, Transaction as Mt940Transaction};
use CommonToolkit\FinancialFormats\Parsers\ISO20022\{CamtParser, PainParser};
use CommonToolkit\FinancialFormats\Parsers\OFX\OfxDocumentParser;
use CommonToolkit\FinancialFormats\Parsers\QIF\QifDocumentParser;
use CommonToolkit\FinancialFormats\Parsers\QXF\QxfDocumentParser;
use CommonToolkit\FinancialFormats\Parsers\Swift\Mt940DocumentParser;
use DateTimeInterface;
use Throwable;

/**
 * Adapter um `php-financial-formats` (Feature 045, „Technischer Zuschnitt").
 * Toolkit-Entities werden NICHT in Controller/Modelle gereicht — hier werden
 * sie in {@see NormalizedStatement}/{@see NormalizedTransaction} überführt.
 *
 * Verifizierte CreditDebit-Semantik (gegen den echten vendor-Code):
 * `CommonToolkit\Enums\CreditDebit::CREDIT` = „Gutschrift / Haben" = Geldeingang
 * aufs eigene Konto ⇒ {@see TransactionDirection::Credit}.
 *
 * MVP-334 (Bauturbo A15): allgemeiner Finanzformat-Import — zusätzlich OFX
 * (SGML+XML), QIF, QXF sowie PAIN.001/008 (Zahlungsaufträge als angekündigte
 * Umsätze). Alle Formate münden in dasselbe interne Schema; Dedup-/
 * Idempotenzregeln (Datei-Hash, Umsatz-Fingerprint) bleiben identisch.
 * QIF/QXF tragen keine Währung — Default EUR (dokumentierte Vereinfachung).
 *
 * Toolkit-Folgepaket 2 (v1.6.2): Sammelbuchungen — trägt eine CAMT-Buchung
 * mehrere TxDtls ({@see \CommonToolkit\FinancialFormats\Entities\ISO20022\Camt\TransactionDetail}),
 * wird je Detail eine {@see NormalizedTransactionDetail} mitgeführt (Betrag
 * signiert, EndToEndId, Mandat, Gegenpartei, Zweck, Rückgabegrund). Buchungen
 * mit genau EINEM TxDtls bleiben byte-identisch zum Bestand (die
 * Einzelwert-Accessors des Toolkits liefern unverändert das erste TxDtls).
 */
final class BankStatementParser {
    /** Fallback-Währung für Formate ohne Währungsangabe (QIF/QXF). */
    private const FALLBACK_CURRENCY = 'EUR';

    /**
     * Inhaltsbasierte Formaterkennung (nie anhand der Dateiendung):
     * OFX-Header/OFX-Wurzel ⇒ OFX; QXF-Wurzel ⇒ QXF; PAIN-Namespace bzw.
     * -Wurzelelement ⇒ PAIN.001/008; XML mit <Document ⇒ CAMT.053;
     * QIF-Bang-Direktive ⇒ QIF; sonst MT940 (Fallback wie bisher).
     */
    public static function detectFormat(string $content): BankStatementFormat {
        $head = ltrim($content);

        if (str_contains($head, 'OFXHEADER') || str_contains($head, '<OFX>') || str_contains($head, '<OFX ') || str_contains($head, '<?OFX')) {
            return BankStatementFormat::Ofx;
        }

        if (str_starts_with($head, '<QXF') || preg_match('/^<\?xml[^>]*\?>\s*<QXF[\s>]/s', $head) === 1) {
            return BankStatementFormat::Qxf;
        }

        if (str_contains($head, 'pain.001') || str_contains($head, '<CstmrCdtTrfInitn')) {
            return BankStatementFormat::Pain001;
        }

        if (str_contains($head, 'pain.008') || str_contains($head, '<CstmrDrctDbtInitn')) {
            return BankStatementFormat::Pain008;
        }

        if (str_contains($head, '<?xml') || str_contains($head, '<Document')) {
            return BankStatementFormat::Camt053;
        }

        if (str_starts_with($head, '!Type:') || str_starts_with($head, '!Account') || str_starts_with($head, '!Option')) {
            return BankStatementFormat::Qif;
        }

        return BankStatementFormat::Mt940;
    }

    /**
     * Parst eine Bankdatei in einen oder mehrere Auszüge (Multi-Statement-Datei).
     *
     * @return list<NormalizedStatement>
     *
     * @throws BankImportException
     */
    public function parse(string $content, BankStatementFormat $format): array {
        FinancialFormatsSupport::ensureAvailable();

        try {
            return match ($format) {
                BankStatementFormat::Camt053 => $this->parseCamt($content),
                BankStatementFormat::Mt940 => $this->parseMt940($content),
                BankStatementFormat::Ofx => $this->parseOfx($content),
                BankStatementFormat::Qif => $this->parseQif($content),
                BankStatementFormat::Qxf => $this->parseQxf($content),
                BankStatementFormat::Pain001 => $this->parsePain001($content),
                BankStatementFormat::Pain008 => $this->parsePain008($content),
            };
        } catch (BankImportException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new BankImportException('parseFailed', $e->getMessage(), ['format' => $format->value]);
        }
    }

    /**
     * @return list<NormalizedStatement>
     */
    private function parseCamt(string $content): array {
        /** @var list<Camt053Document> $documents */
        $documents = CamtParser::parseCamt053All($content);
        $statements = [];

        foreach ($documents as $document) {
            $opening = $document->getOpeningBalance();
            $closing = $document->getClosingBalance();
            $transactions = [];
            $line = 0;

            foreach ($document->getEntries() as $entry) {
                /** @var Camt053Transaction $entry */
                $reference = $entry->getReference();
                $purpose = $entry->getPurpose();
                $refs = ReferenceExtractor::extract($purpose, $reference->getEndToEndId());

                $transactions[] = new NormalizedTransaction(
                    lineIndex: $line++,
                    bookingDate: $this->dateString($entry->getBookingDate()),
                    valutaDate: $this->nullableDateString($entry->getValutaDate()),
                    amount: round($entry->getAmount(), 2),
                    direction: TransactionDirection::fromCreditDebit($entry->getCreditDebit()),
                    currency: $entry->getCurrency()->value,
                    endToEndId: $reference->getEndToEndId(),
                    mandateRef: $reference->getMandateId(),
                    counterpartyName: $entry->getCounterpartyName(),
                    counterpartyIban: $entry->getCounterpartyIban(),
                    purpose: $purpose,
                    extractedRefs: $refs,
                    isReversal: $entry->isReversal(),
                    returnReason: $entry->getReturnReason()?->value,
                    details: $this->camtTransactionDetails($entry),
                );
            }

            $statements[] = new NormalizedStatement(
                format: BankStatementFormat::Camt053,
                accountIban: $document->getAccountIban() !== '' ? $document->getAccountIban() : null,
                openingBalance: $opening !== null ? round($opening->getSignedAmount(), 2) : null,
                closingBalance: $closing !== null ? round($closing->getSignedAmount(), 2) : null,
                periodFrom: $opening !== null ? $this->dateString($opening->getDate()) : null,
                periodTo: $closing !== null ? $this->dateString($closing->getDate()) : null,
                transactions: $transactions,
            );
        }

        if ($statements === []) {
            throw new BankImportException('emptyStatement', (string) __('bank.import.error.empty'), []);
        }

        return $statements;
    }

    /**
     * @return list<NormalizedStatement>
     */
    private function parseMt940(string $content): array {
        $statements = [];

        /** @var iterable<Mt940Document> $documents */
        $documents = Mt940DocumentParser::parseMultiple($content);

        foreach ($documents as $document) {
            $opening = $document->getOpeningBalance();
            $closing = $document->getClosingBalance();
            $transactions = [];
            $line = 0;

            foreach ($document->getTransactions() as $tx) {
                /** @var Mt940Transaction $tx */
                $purposeObject = $tx->getPurposeObject();
                $purposeRaw = $tx->getPurposeRaw();
                $endToEnd = $purposeObject?->getEndToEndReference();
                $mandate = $purposeObject?->getMandateReference();
                $counterpartyName = $this->mt940Counterparty($tx, $purposeObject);
                $refs = ReferenceExtractor::extract($purposeRaw, $endToEnd);

                $transactions[] = new NormalizedTransaction(
                    lineIndex: $line++,
                    bookingDate: $this->dateString($tx->getBookingDate()),
                    valutaDate: $this->nullableDateString($tx->getValutaDate()),
                    amount: round($tx->getAmount(), 2),
                    direction: TransactionDirection::fromCreditDebit($tx->getCreditDebit()),
                    currency: $tx->getCurrency()->value,
                    endToEndId: $endToEnd,
                    mandateRef: $mandate,
                    counterpartyName: $counterpartyName,
                    counterpartyIban: $purposeObject?->getBeneficiaryAccount(),
                    purpose: $purposeRaw,
                    extractedRefs: $refs,
                    isReversal: $tx->isReversal(),
                    returnReason: $purposeObject?->getReturnReason(),
                );
            }

            $statements[] = new NormalizedStatement(
                format: BankStatementFormat::Mt940,
                accountIban: $document->getAccountId() !== '' ? $document->getAccountId() : null,
                openingBalance: round($opening->getSignedAmount(), 2),
                closingBalance: round($closing->getSignedAmount(), 2),
                periodFrom: $this->dateString($opening->getDate()),
                periodTo: $this->dateString($closing->getDate()),
                transactions: $transactions,
            );
        }

        if ($statements === []) {
            throw new BankImportException('emptyStatement', (string) __('bank.import.error.empty'), []);
        }

        return $statements;
    }

    /**
     * OFX 1.x (SGML) / 2.x (XML): je Statement ein Auszug. OFX liefert nur den
     * Schlusssaldo (LEDGERBAL) — die Saldenkette bleibt „unvollständig".
     * Korrektur-Transaktionen (CORRECTACTION) werden als Reversal markiert.
     *
     * @return list<NormalizedStatement>
     */
    private function parseOfx(string $content): array {
        $document = OfxDocumentParser::parse($content);
        $statements = [];

        foreach ($document->getStatements() as $statement) {
            /** @var OfxStatement $statement */
            $transactions = [];
            $line = 0;

            foreach ($statement->getTransactions() as $tx) {
                /** @var OfxTransaction $tx */
                $purpose = trim((string) $tx->getFullDescription());
                $refs = ReferenceExtractor::extract($purpose, $tx->getReferenceNumber(), $tx->getCheckNumber());

                $transactions[] = new NormalizedTransaction(
                    lineIndex: $line++,
                    bookingDate: $this->dateString($tx->getPostedDate()),
                    valutaDate: null,
                    amount: round($tx->getAbsoluteAmount(), 2),
                    direction: TransactionDirection::fromCreditDebit($tx->getCreditDebit()),
                    currency: $tx->getCurrency() ?? $statement->getCurrency(),
                    endToEndId: $tx->getReferenceNumber(),
                    mandateRef: null,
                    counterpartyName: $tx->getName() ?: $tx->getPayee()?->getName(),
                    counterpartyIban: null,
                    purpose: $purpose !== '' ? $purpose : null,
                    extractedRefs: $refs,
                    isReversal: $tx->isCorrection(),
                );
            }

            $ledger = $statement->getLedgerBalance();

            $statements[] = new NormalizedStatement(
                format: BankStatementFormat::Ofx,
                accountIban: null, // OFX führt Kontonummern, keine IBAN.
                openingBalance: null,
                closingBalance: $ledger !== null ? round($ledger->getAmount(), 2) : null,
                periodFrom: $this->dateString($statement->getStartDate()),
                periodTo: $this->dateString($statement->getEndDate()),
                transactions: $transactions,
            );
        }

        return $this->requireStatements($statements);
    }

    /**
     * QIF: ein Auszug je Datei, keine Salden/Währung im Format (Fallback EUR).
     *
     * @return list<NormalizedStatement>
     */
    private function parseQif(string $content): array {
        $document = QifDocumentParser::parse($content);
        $transactions = [];
        $line = 0;

        foreach ($document->getTransactions() as $tx) {
            /** @var QifTransaction $tx */
            $transactions[] = $this->quickenTransaction(
                $line++,
                $this->dateString($tx->getDate()),
                round($tx->getAbsoluteAmount(), 2),
                TransactionDirection::fromCreditDebit($tx->getCreditDebit()),
                $tx->getPayee(),
                $tx->getMemo(),
                $tx->getCheckNumber(),
                null,
            );
        }

        return $this->requireStatements([new NormalizedStatement(
            format: BankStatementFormat::Qif,
            accountIban: null,
            openingBalance: null,
            closingBalance: null,
            periodFrom: null,
            periodTo: null,
            transactions: $transactions,
        )]);
    }

    /**
     * QXF (XML-Schwester von QIF): alle Konten der Datei in einem Auszug.
     *
     * @return list<NormalizedStatement>
     */
    private function parseQxf(string $content): array {
        $document = QxfDocumentParser::parse($content);
        $transactions = [];
        $line = 0;

        foreach ($document->getAllTransactions() as $tx) {
            /** @var QxfTransaction $tx */
            $transactions[] = $this->quickenTransaction(
                $line++,
                $this->dateString($tx->getDate()),
                round($tx->getAbsoluteAmount(), 2),
                TransactionDirection::fromCreditDebit($tx->getCreditDebit()),
                $tx->getPayee(),
                $tx->getMemo(),
                $tx->getCheckNumber(),
                $tx->getReferenceNumber(),
            );
        }

        return $this->requireStatements([new NormalizedStatement(
            format: BankStatementFormat::Qxf,
            accountIban: null,
            openingBalance: null,
            closingBalance: null,
            periodFrom: null,
            periodTo: null,
            transactions: $transactions,
        )]);
    }

    /**
     * PAIN.001 (Überweisungsaufträge): eigene ausgehende Zahlungen als
     * angekündigte Debit-Umsätze (Buchungsdatum = gewünschtes Ausführungsdatum,
     * keine Salden). Gegenpartei = Zahlungsempfänger (Creditor).
     *
     * @return list<NormalizedStatement>
     */
    private function parsePain001(string $content): array {
        /** @var Pain001Document $document */
        $document = PainParser::fromXml001($content);
        $transactions = [];
        $line = 0;
        $accountIban = null;

        foreach ($document->getPaymentInstructions() as $instruction) {
            $accountIban ??= $instruction->getDebtorAccount()->getIban();
            $bookingDate = $this->dateString($instruction->getRequestedExecutionDate());

            foreach ($instruction->getTransactions() as $tx) {
                $remittance = $tx->getRemittanceInformation();
                $purpose = trim((string) $remittance?->getUnstructuredString());
                $endToEnd = $this->nullableRef($tx->getPaymentId()->getEndToEndId());
                $refs = ReferenceExtractor::extract($purpose, $endToEnd, $remittance?->getCreditorReference());

                $transactions[] = new NormalizedTransaction(
                    lineIndex: $line++,
                    bookingDate: $bookingDate,
                    valutaDate: null,
                    amount: round($tx->getAmount(), 2),
                    direction: TransactionDirection::Debit,
                    currency: $tx->getCurrency()->value,
                    endToEndId: $endToEnd,
                    mandateRef: null,
                    counterpartyName: $tx->getCreditor()->getName(),
                    counterpartyIban: $tx->getCreditorAccount()?->getIban(),
                    purpose: $purpose !== '' ? $purpose : null,
                    extractedRefs: $refs,
                    isReversal: false,
                );
            }
        }

        return $this->requireStatements([new NormalizedStatement(
            format: BankStatementFormat::Pain001,
            accountIban: $accountIban,
            openingBalance: null,
            closingBalance: null,
            periodFrom: null,
            periodTo: null,
            transactions: $transactions,
        )]);
    }

    /**
     * PAIN.008 (Lastschriftaufträge): eigene Einzüge als angekündigte
     * Credit-Umsätze (Buchungsdatum = gewünschtes Fälligkeitsdatum, keine
     * Salden). Gegenpartei = Zahlungspflichtiger (Debtor), inkl. Mandat.
     *
     * @return list<NormalizedStatement>
     */
    private function parsePain008(string $content): array {
        /** @var Pain008Document $document */
        $document = PainParser::fromXml008($content);
        $transactions = [];
        $line = 0;
        $accountIban = null;

        foreach ($document->getPaymentInstructions() as $instruction) {
            $accountIban ??= $instruction->getCreditorAccount()->getIban();
            $bookingDate = $this->dateString($instruction->getRequestedCollectionDate());

            foreach ($instruction->getTransactions() as $tx) {
                $remittance = $tx->getRemittanceInformation();
                $purpose = trim((string) $remittance?->getUnstructuredString());
                $endToEnd = $this->nullableRef($tx->getPaymentId()->getEndToEndId());
                $refs = ReferenceExtractor::extract($purpose, $endToEnd, $remittance?->getCreditorReference());

                $transactions[] = new NormalizedTransaction(
                    lineIndex: $line++,
                    bookingDate: $bookingDate,
                    valutaDate: null,
                    amount: round($tx->getAmount(), 2),
                    direction: TransactionDirection::Credit,
                    currency: $tx->getCurrency()->value,
                    endToEndId: $endToEnd,
                    mandateRef: $this->nullableRef($tx->getMandateInfo()->getMandateId()),
                    counterpartyName: $tx->getDebtor()->getName(),
                    counterpartyIban: $tx->getDebtorAccount()->getIban(),
                    purpose: $purpose !== '' ? $purpose : null,
                    extractedRefs: $refs,
                    isReversal: false,
                );
            }
        }

        return $this->requireStatements([new NormalizedStatement(
            format: BankStatementFormat::Pain008,
            accountIban: $accountIban,
            openingBalance: null,
            closingBalance: null,
            periodFrom: null,
            periodTo: null,
            transactions: $transactions,
        )]);
    }

    /**
     * Gemeinsames Mapping der Quicken-Formate (QIF/QXF) — keine Währung im
     * Format, daher {@see self::FALLBACK_CURRENCY}.
     */
    private function quickenTransaction(
        int $line,
        string $bookingDate,
        float $amount,
        TransactionDirection $direction,
        ?string $payee,
        ?string $memo,
        ?string $checkNumber,
        ?string $referenceNumber,
    ): NormalizedTransaction {
        $purpose = trim((string) $memo);
        $refs = ReferenceExtractor::extract($purpose, $payee, $checkNumber, $referenceNumber);

        return new NormalizedTransaction(
            lineIndex: $line,
            bookingDate: $bookingDate,
            valutaDate: null,
            amount: $amount,
            direction: $direction,
            currency: self::FALLBACK_CURRENCY,
            endToEndId: $this->nullableRef($referenceNumber),
            mandateRef: null,
            counterpartyName: $payee,
            counterpartyIban: null,
            purpose: $purpose !== '' ? $purpose : null,
            extractedRefs: $refs,
            isReversal: false,
        );
    }

    /**
     * Einzeltransaktionen einer CAMT-Sammelbuchung (Toolkit-Folgepaket 2):
     * NUR wenn die Buchung mehrere TxDtls trägt, wird die Detail-Liste
     * mitgeführt — bei genau einem TxDtls bleibt das Verhalten byte-identisch
     * zum Bestand (Einzelwert-Accessors decken das erste TxDtls bereits ab).
     *
     * @return list<NormalizedTransactionDetail>
     */
    private function camtTransactionDetails(Camt053Transaction $entry): array {
        if (! $entry->hasMultipleTransactionDetails()) {
            return [];
        }

        $details = [];
        foreach ($entry->getTransactionDetails() as $detail) {
            $details[] = new NormalizedTransactionDetail(
                signedAmount: round($detail->getSignedAmount(), 2),
                endToEndId: $this->nullableRef($detail->getEndToEndId()),
                mandateRef: $this->nullableRef($detail->getMandateId()),
                counterpartyName: $detail->getCounterpartyName(),
                counterpartyIban: $detail->getCounterpartyIban(),
                purpose: $detail->getRemittanceInfo(),
                returnReason: $detail->getReturnReason()?->value,
            );
        }

        return $details;
    }

    /**
     * @param  list<NormalizedStatement>  $statements
     * @return list<NormalizedStatement>
     *
     * @throws BankImportException wenn kein Auszug bzw. kein Umsatz vorhanden ist.
     */
    private function requireStatements(array $statements): array {
        $hasTransactions = false;
        foreach ($statements as $statement) {
            if ($statement->transactions !== []) {
                $hasTransactions = true;
                break;
            }
        }

        if ($statements === [] || ! $hasTransactions) {
            throw new BankImportException('emptyStatement', (string) __('bank.import.error.empty'), []);
        }

        return $statements;
    }

    private function nullableRef(?string $value): ?string {
        $value = trim((string) $value);

        // "NOTPROVIDED" ist der ISO-20022-Platzhalter für „keine Referenz".
        return $value !== '' && strcasecmp($value, 'NOTPROVIDED') !== 0 ? $value : null;
    }

    /**
     * Gegenparteiname aus dem MT940-Verwendungszweck. Bei Gutschriften ist die
     * Gegenpartei der Zahler (Auftraggeber), bei Lastschriften der Empfänger.
     */
    private function mt940Counterparty(Mt940Transaction $tx, ?Mt940Purpose $purpose): ?string {
        if ($purpose === null) {
            return null;
        }

        $candidates = $tx->isCredit()
            ? [$purpose->getPayerName(), $purpose->getOrderingPartyName()]
            : [$purpose->getBeneficiaryName(), $purpose->getPayerName()];

        foreach ($candidates as $candidate) {
            $name = trim((string) $candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    private function dateString(DateTimeInterface $date): string {
        return $date->format('Y-m-d');
    }

    private function nullableDateString(?DateTimeInterface $date): ?string {
        return $date?->format('Y-m-d');
    }
}
