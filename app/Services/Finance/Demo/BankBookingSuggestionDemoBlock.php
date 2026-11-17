<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankBookingSuggestionDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Demo;

use App\Enums\Finance\{AccountType, EuerCategory, MatchStatus, PostingAccountRole, PostingSourceKind, TransactionDirection};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule};
use App\Models\Finance\{BankStatement, BankTransaction};
use App\Models\Platform\Organization;
use App\Services\Accounting\ChartOfAccountsService;
use App\Services\Accounting\Posting\PostingInboxService;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Support\Crypto\{BlindIndex, PurposeKeywordIndex};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{BankHelper, CryptoHelper};

/**
 * Kontierungshilfe in den Demo-Daten (Feature 207, MVP-1660): zwei Monatsmieten
 * per Lastschrift sind schon aufs Mietkonto gebucht, drei weitere stehen offen —
 * die Seite „Kontierungsvorschläge“ zeigt sie als eine Gruppe mit Begründung.
 * Alles erfunden: Gläubiger-ID aus dem Beispiel der Bundesbank, IBAN mit
 * Bankleitzahl 99999999. Läuft nach dem Finanzblock, der die lokale
 * Buchhaltung einrichtet; ohne sie bleibt der Block leer.
 */
final class BankBookingSuggestionDemoBlock implements DemoBlock {
    private const string CREDITOR = 'DE98ZZZ09999999999';

    private const string MANDATE = 'DEMO-MIETE-0001';

    public function supports(DemoSeedContext $context): bool {
        return $context->moduleActive('module.finance');
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;
        $actor = $context->users->first() ?? $context->actor;
        $bank = AccountingAccount::query()->where('organization_id', $organization->id)->where('is_bank', true)->orderBy('number')->first();
        if (! $bank instanceof AccountingAccount) {
            return ['bank_booking_suggestions' => 0];
        }

        try {
            $rent = AccountingAccount::query()->where('organization_id', $organization->id)->where('number', '4210')->first()
                ?? app(ChartOfAccountsService::class)->create($organization, [
                    'number' => '4210',
                    'name' => 'Miete (Demo)',
                    'type' => AccountType::Expense,
                    'euer_category' => EuerCategory::Expense->value,
                    'datev_account' => '4210',
                ]);
            $this->ensureBankRule($organization, $bank);

            $statement = BankStatement::query()->create([
                'organization_id' => $organization->id,
                'source_format' => \App\Enums\Finance\BankStatementFormat::Camt053,
                'file_path' => 'imports/bank/demo/kontierungshilfe.xml',
                'file_hash' => (string) CryptoHelper::hash('demo-kontierungshilfe-' . $organization->id, HashAlgorithm::SHA256),
                'opening_balance' => '10000.00',
                'closing_balance' => '5000.00',
                'period_from' => CarbonImmutable::now()->startOfYear()->toDateString(),
                'period_to' => CarbonImmutable::now()->startOfYear()->addMonths(5)->endOfMonth()->toDateString(),
                'tx_count' => 5,
                'balance_check' => \App\Enums\Finance\BalanceCheck::Unknown,
            ]);

            $inbox = app(PostingInboxService::class);
            $count = 0;
            foreach (range(0, 4) as $month) {
                $transaction = $this->transaction($organization, $statement, $month);
                if ($month < 2) {
                    $inbox->postBankTransactionToAccount($organization, $transaction, $rent, null, null, 'Miete Büro (Demo)', $actor);
                }
                $count++;
            }

            return ['bank_booking_suggestions' => $count];
        } catch (\Throwable $exception) {
            report($exception);

            return ['bank_booking_suggestions' => 0];
        }
    }

    public function purge(Organization $organization): void {}

    private function transaction(Organization $organization, BankStatement $statement, int $month): BankTransaction {
        $date = CarbonImmutable::now()->startOfYear()->addMonths($month)->addDays(2);
        $iban = BankHelper::generateGermanIBAN('99999999', '0000004711');
        $purpose = 'Miete Büro Musterstraße ' . $date->format('m/Y');

        return BankTransaction::query()->create([
            'organization_id' => $organization->id,
            'bank_statement_id' => $statement->id,
            'line_index' => $month,
            'booking_date' => $date->toDateString(),
            'valuta_date' => $date->toDateString(),
            'amount' => '1250.00',
            'direction' => TransactionDirection::Debit,
            'currency' => 'EUR',
            'mandate_ref' => self::MANDATE,
            'creditor_identifier' => self::CREDITOR,
            'counterparty_name' => 'Muster Hausverwaltung GmbH (Demo)',
            'counterparty_iban' => $iban,
            'counterparty_iban_hash' => BlindIndex::ofIban($iban),
            'purpose' => $purpose,
            'purpose_keyword_hash' => PurposeKeywordIndex::hash($purpose),
            'extracted_refs' => [],
            'is_reversal' => false,
            'fingerprint' => (string) CryptoHelper::hash('demo-kontierungshilfe-' . $organization->id . '-' . $month, HashAlgorithm::SHA256),
            'match_status' => MatchStatus::Unmatched,
        ]);
    }

    /** Direktbuchungen brauchen das Bankkonto der Zahlungsregel; die Demo legt es an, wenn es fehlt. */
    private function ensureBankRule(Organization $organization, AccountingAccount $bank): void {
        $exists = AccountingPostingRule::query()
            ->where('organization_id', $organization->id)
            ->where('source_kind', PostingSourceKind::Payment->value)
            ->where('role', PostingAccountRole::Bank->value)
            ->exists();
        if ($exists) {
            return;
        }

        AccountingPostingRule::query()->create([
            'organization_id' => $organization->id,
            'source_kind' => PostingSourceKind::Payment,
            'role' => PostingAccountRole::Bank,
            'accounting_account_id' => $bank->id,
            'priority' => 100,
            'version' => 1,
            'valid_from' => CarbonImmutable::now()->startOfYear()->toDateString(),
            'is_active' => true,
        ]);
    }
}
