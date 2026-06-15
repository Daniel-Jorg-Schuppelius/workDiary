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
use CommonToolkit\FinancialFormats\Entities\Swift\Mt9xx\Purpose as Mt940Purpose;
use CommonToolkit\FinancialFormats\Entities\Swift\Mt9xx\Type940\{Document as Mt940Document, Transaction as Mt940Transaction};
use CommonToolkit\FinancialFormats\Parsers\ISO20022\CamtParser;
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
 */
final class BankStatementParser {
    /** Pragmatische Formaterkennung: XML mit <Document ⇒ CAMT, sonst MT940. */
    public static function detectFormat(string $content): BankStatementFormat {
        $head = ltrim($content);

        return str_contains($head, '<?xml') || str_contains($head, '<Document')
            ? BankStatementFormat::Camt053
            : BankStatementFormat::Mt940;
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
            return $format === BankStatementFormat::Camt053
                ? $this->parseCamt($content)
                : $this->parseMt940($content);
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
