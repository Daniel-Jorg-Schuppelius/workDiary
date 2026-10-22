<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\{BalanceCheck, MatchStatus, StatementChainCheck};
use App\Models\Finance\{BankAccount, BankStatement, BankTransaction};
use App\Models\Platform\User;
use App\Services\Calendar\HolidayService;
use App\Services\Concerns\ResolvesActorId;
use App\Services\Finance\Banking\{BankStatementParser, NormalizedStatement, NormalizedTransaction};
use App\Support\Crypto\BlindIndex;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, NumberHelper};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};

/**
 * Bankimport (Feature 045, „Phase 4"): erkennt das Format, parst über den
 * {@see BankStatementParser}, legt Auszug + Umsätze im Prüfbereich an und ändert
 * KEINE Rechnungs-/Spesenstatus (das tut erst der {@see ReconciliationService}).
 *
 * Schutzmechanismen:
 *  - Datei-Dublette: gleicher `file_hash` je Organisation ⇒ Abbruch.
 *  - Umsatz-Dublette: gleicher `fingerprint` (org-weit) ⇒ überspringen (zählen).
 *  - Saldenkette: opening + Σ signierte Beträge ≈ closing (Toleranz 1 ct) ⇒
 *    balance_check.
 *  - Auto-Zuordnung des eigenen Bankkontos über statement_iban_hash.
 *  - Vorläufige Formate (CAMT.052/054, PAIN; MVP-1274) bleiben Vorschau, bis
 *    ein verbindlicher Auszug desselben Kontos sie ersetzt.
 *  - Anschluss an den Vorauszug (MVP-1279): Eröffnungssaldo = Schlusssaldo
 *    des Vorgängers, dazwischen kein Bankarbeitstag ohne Auszug.
 */
class BankImportService {
    use ResolvesActorId;

    private const STORAGE_DISK = 'local';

    private const BASE_PATH = 'imports/bank';

    private const BALANCE_TOLERANCE = 0.01;

    public function __construct(private readonly BankStatementParser $parser) {}

    /**
     * Importiert eine hochgeladene Bankdatei. Liefert die angelegten Auszüge.
     *
     * @return list<BankStatement>
     *
     * @throws BankImportException
     */
    public function import(UploadedFile $file, int $organizationId, ?BankAccount $bankAccount = null, ?User $actor = null): array {
        return $this->importContent((string) $file->get(), $file->getClientOriginalName(), $organizationId, $bankAccount, $actor);
    }

    /**
     * Importiert Dateiinhalt ohne Upload — etwa einen per EBICS abgerufenen Auszug (MVP-124).
     *
     * @return list<BankStatement>
     *
     * @throws BankImportException
     */
    public function importContent(string $content, string $filename, int $organizationId, ?BankAccount $bankAccount = null, ?User $actor = null): array {
        if (trim($content) === '') {
            throw new BankImportException('emptyFile', (string) __('bank.import.error.empty_file'), []);
        }

        // CryptoHelper::hash liefert null nur bei null-Input; $content ist hier
        // garantiert ein nicht-leerer String — der Fallback ist reine Typ-Ebene.
        $fileHash = CryptoHelper::hash($content);

        if (BankStatement::query()
            ->where('organization_id', $organizationId)
            ->where('file_hash', $fileHash)
            ->exists()
        ) {
            throw new BankImportException('duplicateFile', (string) __('bank.import.error.duplicate_file'), [
                'file_hash' => $fileHash,
            ]);
        }

        $format = BankStatementParser::detectFormat($content);
        $normalizedStatements = $this->parser->parse($content, $format);

        $filePath = $this->storeFile($content, $filename, $organizationId, $fileHash);
        $actorId = $this->resolveActorId($actor);

        $created = [];
        foreach ($normalizedStatements as $index => $normalized) {
            $created[] = $this->persistStatement(
                $normalized,
                $organizationId,
                $bankAccount,
                $filePath,
                // Mehrere Auszüge je Datei: file_hash je Auszug eindeutig machen.
                count($normalizedStatements) > 1 ? $fileHash . '-' . $index : $fileHash,
                $actorId,
            );
        }

        foreach ($created as $statement) {
            if ($statement->is_provisional) {
                $this->supersedeIfCovered($statement);
            } else {
                $this->supersedeProvisional($statement);
                $this->recheckChain($statement);
            }
        }

        // Vollaudit 2026-07 (M16): Registry-Anbindung der Finanzschnittstelle —
        // Saldensprung (Mismatch) und ungeklärte Zahlungseingänge melden.
        $this->notifyImportFindings($created);

        // Feature-Nutzungszähler (036; Vollaudit 2026-07, N14).
        app(\App\Services\Metrics\OperationsMetricsService::class)->increment('finance.bank_import', $organizationId);

        return $created;
    }

    /**
     * Benachrichtigungen nach dem Import (Vollaudit 2026-07, M16): je Auszug
     * mit Saldenketten-Mismatch ein FinanceBankImportFailed; für ungeklärte
     * Transaktionen EIN gebündeltes FinanceReconciliationReview.
     *
     * @param  list<BankStatement>  $statements
     */
    private function notifyImportFindings(array $statements): void {
        $dispatcher = app(\App\Services\Notification\NotificationDispatcher::class);

        $unmatchedTotal = 0;
        foreach ($statements as $statement) {
            if ($statement->is_provisional) {
                continue;
            }
            $unmatchedTotal += (int) BankTransaction::query()
                ->where('bank_statement_id', $statement->id)
                ->where('match_status', MatchStatus::Unmatched)
                ->count();

            if ($statement->balance_check === BalanceCheck::Mismatch) {
                $dispatcher->notify(
                    \App\Enums\Notification\NotificationEvent::FinanceBankImportFailed,
                    $statement,
                    null,
                    [
                        'title' => (string) __('notification.message.finance_bank_import_failed_title'),
                        'title_key' => 'notification.message.finance_bank_import_failed_title',
                        'title_params' => [],
                        'message' => null,
                        'url' => route('finance.reconciliation.show', $statement),
                    ],
                );
            }
        }

        if ($unmatchedTotal > 0) {
            $dispatcher->notify(
                \App\Enums\Notification\NotificationEvent::FinanceReconciliationReview,
                collect($statements)->firstWhere('is_provisional', false) ?? $statements[0],
                null,
                [
                    'title' => (string) __('notification.message.finance_reconciliation_review_title', ['count' => $unmatchedTotal]),
                    'title_key' => 'notification.message.finance_reconciliation_review_title',
                    'title_params' => ['count' => $unmatchedTotal],
                    'message' => null,
                    'url' => route('finance.reconciliation.index'),
                ],
            );
        }
    }

    private function persistStatement(
        NormalizedStatement $normalized,
        int $organizationId,
        ?BankAccount $bankAccount,
        string $filePath,
        string $fileHash,
        ?int $actorId,
    ): BankStatement {
        $statementIbanHash = BlindIndex::ofIban($normalized->accountIban);
        $resolvedAccount = $bankAccount ?? $this->resolveAccount($organizationId, $statementIbanHash);
        $provisional = $normalized->format->isProvisional();
        // Vorläufige Formate tragen keine Salden; ihr Zeitraum sind die Buchungstage.
        $bookingDates = array_map(static fn (NormalizedTransaction $tx): string => $tx->bookingDate, $normalized->transactions);
        $periodFrom = $normalized->periodFrom ?? ($provisional && $bookingDates !== [] ? min($bookingDates) : null);
        $periodTo = $normalized->periodTo ?? ($provisional && $bookingDates !== [] ? max($bookingDates) : null);

        return DB::transaction(function () use (
            $normalized,
            $organizationId,
            $resolvedAccount,
            $filePath,
            $fileHash,
            $statementIbanHash,
            $actorId,
            $provisional,
            $periodFrom,
            $periodTo,
        ): BankStatement {
            /** @var BankStatement $statement */
            $statement = BankStatement::query()->create([
                'organization_id' => $organizationId,
                'bank_account_id' => $resolvedAccount?->id,
                'source_format' => $normalized->format,
                'is_provisional' => $provisional,
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'statement_iban_hash' => $statementIbanHash,
                'opening_balance' => $normalized->openingBalance !== null ? (string) $normalized->openingBalance : null,
                'closing_balance' => $normalized->closingBalance !== null ? (string) $normalized->closingBalance : null,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'tx_count' => 0,
                'balance_check' => BalanceCheck::Unknown,
                'imported_by_user_id' => $actorId,
            ]);

            $imported = 0;
            $sum = 0.0;
            foreach ($normalized->transactions as $tx) {
                $sum += $tx->direction->sign() * $tx->amount;
                if ($this->persistTransaction($statement, $organizationId, $tx)) {
                    $imported++;
                }
            }

            $statement->tx_count = $imported;
            $statement->balance_check = $this->checkBalanceChain($normalized, $sum);
            $statement->save();

            return $statement->refresh();
        });
    }

    /** @return bool true, wenn neu angelegt; false bei Fingerprint-Dublette. */
    private function persistTransaction(BankStatement $statement, int $organizationId, NormalizedTransaction $tx): bool {
        $fingerprint = $this->fingerprint($statement, $tx);

        $exists = BankTransaction::query()
            ->where('organization_id', $organizationId)
            ->where('fingerprint', $fingerprint)
            ->exists();

        if ($exists) {
            return false;
        }

        BankTransaction::query()->create([
            'organization_id' => $organizationId,
            'bank_statement_id' => $statement->id,
            'line_index' => $tx->lineIndex,
            'booking_date' => $tx->bookingDate,
            'valuta_date' => $tx->valutaDate,
            'amount' => (string) round($tx->amount, 2),
            'direction' => $tx->direction,
            'currency' => $tx->currency,
            'end_to_end_id' => $tx->endToEndId,
            'mandate_ref' => $tx->mandateRef,
            'counterparty_name' => $tx->counterpartyName,
            'counterparty_iban' => $tx->counterpartyIban,
            'counterparty_iban_hash' => BlindIndex::ofIban($tx->counterpartyIban),
            'purpose' => $tx->purpose,
            'extracted_refs' => $tx->extractedRefs,
            'is_reversal' => $tx->isReversal,
            'return_reason' => $tx->returnReason,
            // Sammelbuchung (Toolkit-Folgepaket 2): Detail-Liste NUR bei
            // mehreren TxDtls; sonst NULL (nie leerer encrypted-Payload).
            'transaction_details' => $tx->details !== [] ? $this->detailRows($tx) : null,
            'fingerprint' => $fingerprint,
            'match_status' => MatchStatus::Unmatched,
        ]);

        return true;
    }

    /**
     * Persistenzform der Einzeltransaktionen einer Sammelbuchung: Betrag
     * signiert (Haben +, Soll −), Referenzen, Gegenpartei inkl. IBAN-Hash
     * (Matching-Ableitung wie counterparty_iban_hash), Zweck und
     * Rückgabegrund je Detail. Liegt als Ganzes verschlüsselt at-rest.
     *
     * @return list<array<string, mixed>>
     */
    private function detailRows(NormalizedTransaction $tx): array {
        $rows = [];
        foreach ($tx->details as $detail) {
            $rows[] = [
                'amount' => NumberHelper::toUSFormat($detail->signedAmount, 2),
                'end_to_end_id' => $detail->endToEndId,
                'mandate_ref' => $detail->mandateRef,
                'counterparty_name' => $detail->counterpartyName,
                'counterparty_iban' => $detail->counterpartyIban,
                'counterparty_iban_hash' => BlindIndex::ofIban($detail->counterpartyIban),
                'purpose' => $detail->purpose,
                'return_reason' => $detail->returnReason,
            ];
        }

        return $rows;
    }

    /**
     * Stabiler Fingerprint je Umsatz (Dublettenschutz bei Re-Import).
     * Bewusst ohne PII: Datei-Hash, Zeile, Betrag, Richtung, Datum, Refs.
     */
    private function fingerprint(BankStatement $statement, NormalizedTransaction $tx): string {
        $refs = $tx->extractedRefs;
        sort($refs);

        // implode liefert nie null — der Fallback ist reine Typ-Ebene
        // (CryptoHelper::hash ist nur für null-Input nullable).
        return CryptoHelper::hash(implode('|', [
            $statement->file_hash,
            $tx->lineIndex,
            NumberHelper::toUSFormat($tx->amount, 2),
            $tx->direction->value,
            $tx->bookingDate,
            $tx->endToEndId ?? '',
            implode(',', $refs),
        ]));
    }

    /** opening + Σ signierte Beträge ≈ closing (Toleranz 1 ct). */
    private function checkBalanceChain(NormalizedStatement $normalized, float $sum): BalanceCheck {
        if ($normalized->openingBalance === null || $normalized->closingBalance === null) {
            return BalanceCheck::Unknown;
        }

        $expected = $normalized->openingBalance + $sum;

        return abs($expected - $normalized->closingBalance) <= self::BALANCE_TOLERANCE
            ? BalanceCheck::Ok
            : BalanceCheck::Mismatch;
    }

    /**
     * Vorläufige Auszüge desselben Kontos bis zum Ende des verbindlichen gelten
     * als ersetzt (MVP-1274); ihre Umsätze bleiben zur Nachvollziehbarkeit stehen.
     */
    private function supersedeProvisional(BankStatement $final): void {
        if ($final->period_to === null) {
            return;
        }

        BankStatement::query()
            ->sameAccountAs($final)
            ->where('is_provisional', true)
            ->whereNull('superseded_at')
            ->where('period_to', '<', DateRange::dayAfter($final->period_to))
            ->get()
            ->each(static fn (BankStatement $provisional) => $provisional->update(['superseded_at' => now()]));
    }

    /** Kam der verbindliche Auszug zuerst, ist der vorläufige gleich ersetzt. */
    private function supersedeIfCovered(BankStatement $provisional): void {
        $covered = $provisional->period_to !== null && BankStatement::query()
            ->sameAccountAs($provisional)
            ->where('is_provisional', false)
            ->where('period_to', '>=', DateRange::day($provisional->period_to))
            ->exists();
        if ($covered) {
            $provisional->update(['superseded_at' => now()]);
        }
    }

    /**
     * Prüft den Anschluss des Auszugs und den seines Nachfolgers — ein
     * nachgereichter Auszug schließt dessen Lücke (MVP-1279).
     */
    private function recheckChain(BankStatement $statement): void {
        $statement->update(['chain_check' => $this->chainCheck($statement)]);

        $successor = $statement->period_to !== null
            ? BankStatement::query()
                ->sameAccountAs($statement)
                ->where('is_provisional', false)
                ->whereNotNull('opening_balance')
                ->where('period_from', '>=', DateRange::day($statement->period_to))
                ->orderBy('period_from')
                ->orderBy('id')
                ->first()
            : null;
        $successor?->update(['chain_check' => $this->chainCheck($successor)]);
    }

    private function chainCheck(BankStatement $statement): ?StatementChainCheck {
        if ($statement->opening_balance === null || $statement->period_from === null) {
            return null;
        }

        $previous = $statement->previousStatement();
        if ($previous === null || $previous->period_to === null) {
            return StatementChainCheck::First;
        }

        if (abs((float) $statement->opening_balance - (float) $previous->closing_balance) > self::BALANCE_TOLERANCE) {
            return StatementChainCheck::Mismatch;
        }

        // Wochenenden und Feiertage im Rechtsraum der Organisation sind keine Lücke.
        $nextBusinessDay = app(HolidayService::class)->nextBusinessDay($previous->period_to->copy()->addDay());

        return $nextBusinessDay->lt($statement->period_from) ? StatementChainCheck::Gap : StatementChainCheck::Ok;
    }

    private function resolveAccount(int $organizationId, ?string $ibanHash): ?BankAccount {
        if ($ibanHash === null) {
            return null;
        }

        return BankAccount::query()
            ->where('organization_id', $organizationId)
            ->where('iban_hash', $ibanHash)
            ->first();
    }

    private function storeFile(string $content, string $filename, int $organizationId, string $fileHash): string {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'dat';
        $path = sprintf(
            '%s/%d/%s/%s.%s',
            self::BASE_PATH,
            $organizationId,
            CarbonImmutable::now()->format('Y-m'),
            $fileHash,
            $extension,
        );

        Storage::disk(self::STORAGE_DISK)->put($path, $content);

        return $path;
    }

}
