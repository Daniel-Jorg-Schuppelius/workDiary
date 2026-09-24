<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Demo;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use Illuminate\Support\Collection;

/** Kassenbuch mit Tagesabschluss und lokale Buchhaltung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class FinanceDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'cash_book' => $context->mainCustomer === null ? 0 : $this->seedCashBook($context->organization, $context->mainCustomer, $context->users),
            'local_accounting' => $this->seedLocalAccounting($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * Barkasse mit zwei Buchungen und Tagesabschluss (MVP-414, Phase 38).
     *
     * @param  Collection<int, User>  $users
     */
    private function seedCashBook(Organization $organization, Customer $customer, Collection $users): int {
        /** @var User|null $actor */
        $actor = $users->first();
        if ($actor === null) {
            return 0;
        }
        $count = 0;

        // 2) Barkasse mit zwei Buchungen und Tagesabschluss (MVP-414).
        if ($this->moduleActive('module.kasse')) {
            try {
                $register = \App\Models\Finance\CashRegister::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'name' => (string) __('Demo-Barkasse'),
                ], [
                    'currency' => 'EUR',
                    'opening_balance' => '150.00',
                    'opened_on' => \Illuminate\Support\Carbon::now()->subDays(10)->toDateString(),
                    'active' => true,
                ]);
                if ($register->wasRecentlyCreated) {
                    $cash = app(\App\Services\Finance\CashBookService::class);
                    $bookedOn = \Illuminate\Support\Carbon::now()->subDay();
                    $cash->record($register, [
                        'booked_on' => $bookedOn->toDateString(),
                        'direction' => \App\Models\Finance\CashEntry::DIRECTION_IN,
                        'amount' => 250.00,
                        'purpose' => (string) __('Barverkauf Kleinmaterial (Demo)'),
                        'tax_rate' => 19,
                        'created_by' => $actor->id,
                    ]);
                    $cash->record($register, [
                        'booked_on' => $bookedOn->toDateString(),
                        'direction' => \App\Models\Finance\CashEntry::DIRECTION_OUT,
                        'amount' => 40.00,
                        'purpose' => (string) __('Büromaterial (Demo)'),
                        'tax_rate' => 19,
                        'created_by' => $actor->id,
                    ]);
                    $cash->closeDay($register, $bookedOn, $cash->balanceAsOf($register, $bookedOn), (string) __('Demo-Tagesabschluss'), $actor->id);
                }
                $count++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('Demo-Seeder: Kassenbuch übersprungen: ' . $e->getMessage());
            }
        }

        return $count;
    }

    private function seedLocalAccounting(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.finance')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $startsOn = \Carbon\CarbonImmutable::now()->startOfYear();

            app(\App\Services\Accounting\AccountingProfileService::class)->configure($organization, [
                'profit_determination' => \App\Enums\Finance\ProfitDetermination::DoubleEntry,
                'base_currency' => \CommonToolkit\Enums\CurrencyCode::Euro,
                'fiscal_year_start_month' => 1,
                'starts_on' => $startsOn,
                'note' => null,
            ]);

            app(\App\Services\Accounting\FiscalYearService::class)->create($organization, $startsOn);
            app(\App\Services\Accounting\AccountingProfileService::class)->activateLocal($organization, $actor);

            $chart = app(\App\Services\Accounting\ChartOfAccountsService::class);
            /** @var array<string, \App\Models\Accounting\AccountingAccount> $accounts */
            $accounts = [];
            foreach ([
                ['receivable', '1400', 'Forderungen aus L+L', \App\Enums\Finance\AccountType::Asset, true],
                ['bank', '1200', 'Bank', \App\Enums\Finance\AccountType::Asset, false],
                ['vat', '1776', 'Umsatzsteuer 19 %', \App\Enums\Finance\AccountType::Liability, false],
                ['revenue', '8400', 'Erlöse 19 %', \App\Enums\Finance\AccountType::Income, false],
                ['expense', '6300', 'Sonstige Aufwendungen', \App\Enums\Finance\AccountType::Expense, false],
            ] as [$key, $number, $name, $type, $openItem]) {
                $accounts[$key] = $chart->create($organization, [
                    'number' => $number,
                    'name' => $name,
                    'type' => $type,
                    'is_open_item' => $openItem,
                    'is_bank' => $key === 'bank',
                    'datev_account' => $number,
                ]);
            }

            foreach ([
                [\App\Enums\Finance\PostingAccountRole::Receivable, 'receivable', null],
                [\App\Enums\Finance\PostingAccountRole::Revenue, 'revenue', ['tax_rate' => '19.00']],
                [\App\Enums\Finance\PostingAccountRole::TaxOutput, 'vat', ['tax_rate' => '19.00']],
            ] as [$role, $accountKey, $match]) {
                \App\Models\Accounting\AccountingPostingRule::query()->create([
                    'organization_id' => $organization->id,
                    'source_kind' => \App\Enums\Finance\PostingSourceKind::SalesInvoice,
                    'role' => $role,
                    'accounting_account_id' => $accounts[$accountKey]->id,
                    'match_criteria' => $match,
                    'priority' => 100,
                    'version' => 1,
                    'valid_from' => $startsOn->toDateString(),
                    'is_active' => true,
                ]);
            }

            app(\App\Services\Accounting\JournalService::class)->postDirect($organization, [
                'booked_on' => $startsOn->addMonth(),
                'memo' => 'Demo-Erlösbuchung',
                'document_reference' => 'DEMO-1',
                'source_key' => 'demo:accounting:1',
                'snapshot' => ['due_date' => $startsOn->addMonth()->addDays(14)->toDateString()],
                'lines' => [
                    ['accounting_account_id' => $accounts['receivable']->id, 'debit' => '1190.00', 'credit' => '0.00'],
                    ['accounting_account_id' => $accounts['revenue']->id, 'debit' => '0.00', 'credit' => '1000.00'],
                    ['accounting_account_id' => $accounts['vat']->id, 'debit' => '0.00', 'credit' => '190.00'],
                ],
            ], $actor);

            return count($accounts);
        } catch (\Throwable $exception) {
            report($exception);

            return 0;
        }
    }
}
