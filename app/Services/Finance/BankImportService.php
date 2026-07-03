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

use App\Enums\Finance\{BalanceCheck, MatchStatus};
use App\Models\Finance\{BankAccount, BankStatement, BankTransaction};
use App\Models\User;
use App\Services\Finance\Banking\{BankStatementParser, NormalizedStatement, NormalizedTransaction};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{BankHelper, CryptoHelper};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, DB, Storage};

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
 */
class BankImportService {
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
        $content = (string) $file->get();
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

        $filePath = $this->storeFile($file, $organizationId, $fileHash);
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

        return $created;
    }

    private function persistStatement(
        NormalizedStatement $normalized,
        int $organizationId,
        ?BankAccount $bankAccount,
        string $filePath,
        string $fileHash,
        ?int $actorId,
    ): BankStatement {
        $statementIbanHash = BankHelper::hashIBAN($normalized->accountIban);
        $resolvedAccount = $bankAccount ?? $this->resolveAccount($organizationId, $statementIbanHash);

        return DB::transaction(function () use (
            $normalized,
            $organizationId,
            $resolvedAccount,
            $filePath,
            $fileHash,
            $statementIbanHash,
            $actorId,
        ): BankStatement {
            /** @var BankStatement $statement */
            $statement = BankStatement::query()->create([
                'organization_id' => $organizationId,
                'bank_account_id' => $resolvedAccount?->id,
                'source_format' => $normalized->format,
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'statement_iban_hash' => $statementIbanHash,
                'opening_balance' => $normalized->openingBalance !== null ? (string) $normalized->openingBalance : null,
                'closing_balance' => $normalized->closingBalance !== null ? (string) $normalized->closingBalance : null,
                'period_from' => $normalized->periodFrom,
                'period_to' => $normalized->periodTo,
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
            'counterparty_iban_hash' => BankHelper::hashIBAN($tx->counterpartyIban),
            'purpose' => $tx->purpose,
            'extracted_refs' => $tx->extractedRefs,
            'is_reversal' => $tx->isReversal,
            'fingerprint' => $fingerprint,
            'match_status' => MatchStatus::Unmatched,
        ]);

        return true;
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
            number_format($tx->amount, 2, '.', ''),
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

    private function resolveAccount(int $organizationId, ?string $ibanHash): ?BankAccount {
        if ($ibanHash === null) {
            return null;
        }

        return BankAccount::query()
            ->where('organization_id', $organizationId)
            ->where('iban_hash', $ibanHash)
            ->first();
    }

    private function storeFile(UploadedFile $file, int $organizationId, string $fileHash): string {
        $extension = $file->getClientOriginalExtension() ?: 'dat';
        $path = sprintf(
            '%s/%d/%s/%s.%s',
            self::BASE_PATH,
            $organizationId,
            CarbonImmutable::now()->format('Y-m'),
            $fileHash,
            $extension,
        );

        Storage::disk(self::STORAGE_DISK)->put($path, (string) $file->get());

        return $path;
    }

    private function resolveActorId(?User $actor): ?int {
        $id = $actor->id ?? Auth::id();

        return $id !== null ? (int) $id : null;
    }
}
