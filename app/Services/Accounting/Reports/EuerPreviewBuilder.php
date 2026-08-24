<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EuerPreviewBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\{AccountType, EuerCategory, SettlementKind};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingOpenItem};
use App\Models\Organization;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * EÜR-Vorschau nach Formularzeilen (§ 4 Abs. 3 EStG) — Feature 125, MVP-676.
 *
 * Eine **prüfbare Vorschau**, keine Erklärung — sie kennzeichnet Methode,
 * Zeitraum und ungeklärte Fälle.
 */
class EuerPreviewBuilder extends AbstractAccountingReportBuilder {
    /**
     * Grundregel ist § 11 EStG: gezählt wird, was zu- oder abgeflossen ist.
     * Zwei Quellen ergeben zusammen den Zahlungsstrom, ohne einander zu
     * überschneiden — direkte Geldbuchungen (Kassenbeleg, Lastschrift) mit
     * ihren Erfolgszeilen, und Ausgleiche offener Posten anteilig mit den
     * Kategorien des Ursprungsbelegs. Die Ausgleichsbuchung selbst trägt nur
     * Bestandskonten und fällt daher nicht doppelt ins Gewicht.
     *
     * Abschreibungen kommen nicht aus Zahlungen: § 4 Abs. 3 S. 3 EStG nimmt
     * das Anlagevermögen von § 11 aus. Sie werden aus dem Journal gelesen und
     * als manuell zu prüfen gekennzeichnet.
     *
     * @return array{rows: list<array<string, mixed>>, income: string, expense: string, result: string, not_deductible: string, unclear: list<string>}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<string, array{gross: numeric-string, deductible: numeric-string}> $buckets */
        $buckets = [];
        /** @var array<int, AccountingAccount> $unclearAccounts */
        $unclearAccounts = [];

        foreach ($this->cashEffectiveEntries($organization, $from, $to) as $entry) {
            foreach ($entry->lines as $line) {
                $account = $line->account;
                if (! $account instanceof AccountingAccount || $account->is_bank || $account->is_cash) {
                    continue;
                }

                $this->collectEuer($buckets, $unclearAccounts, $account, $this->directionalAmount($account, $line->signedAmount()->getAmount()));
            }
        }

        $unclear = [];

        foreach ($this->settlementsInPeriod($organization, $from, $to) as $settlement) {
            $item = $settlement->openItem;
            if (! $item instanceof AccountingOpenItem) {
                $unclear[] = (string) __('accounting.reports.unclear.settlement_without_item', ['id' => (string) $settlement->id]);

                continue;
            }

            $entry = $item->entry;
            $original = $item->original_amount;
            if ($entry === null || $original === null || $original->isZero()) {
                $unclear[] = (string) __('accounting.reports.unclear.settlement_without_source', ['id' => (string) $settlement->id]);

                continue;
            }

            $settled = $settlement->amount ?? Money::zero($item->currency);
            $reversal = $settlement->kind === SettlementKind::Reversal;

            foreach ($entry->lines as $line) {
                $account = $line->account;
                if (! $account instanceof AccountingAccount || $account->is_open_item || $account->is_bank || $account->is_cash) {
                    continue;
                }

                $share = $this->proRata($this->directionalAmount($account, $line->signedAmount()->getAmount()), $settled, $original);
                $this->collectEuer($buckets, $unclearAccounts, $account, $reversal ? NumberHelper::negatePrecise($share) : $share);
            }
        }

        // Abschreibungen stammen aus dem Journal, nicht aus dem Zahlungsstrom.
        $bookedOnly = $this->sumsByAccount($organization, $from, $to);
        $depreciationAccounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->where('euer_category', EuerCategory::Depreciation->value)
            ->get();

        foreach ($depreciationAccounts as $account) {
            $signed = $this->directionalAmount(
                $account,
                NumberHelper::subtractPrecise($bookedOnly[$account->id]['debit'] ?? '0.00', $bookedOnly[$account->id]['credit'] ?? '0.00', 2),
            );
            if (NumberHelper::isZeroPrecise($signed)) {
                continue;
            }

            $this->collectEuer($buckets, $unclearAccounts, $account, $signed);
        }

        $rows = [];
        $income = '0.00';
        $expense = '0.00';
        $notDeductible = '0.00';

        foreach (EuerCategory::cases() as $category) {
            $bucket = $buckets[$category->value] ?? null;
            if ($bucket === null || (NumberHelper::isZeroPrecise($bucket['gross']) && NumberHelper::isZeroPrecise($bucket['deductible']))) {
                continue;
            }

            // Der nicht abziehbare Rest ist keine stille Differenz — er wird
            // ausgewiesen, damit die Kürzung nachvollziehbar bleibt.
            $rest = NumberHelper::subtractPrecise($bucket['gross'], $bucket['deductible'], 2);

            $rows[] = [
                'category' => $category,
                'gross' => $bucket['gross'],
                'deductible' => $bucket['deductible'],
                'not_deductible' => $rest,
                'manual' => ! $category->derivedFromPayments(),
            ];

            if ($category->isIncome()) {
                $income = NumberHelper::addPrecise($income, $bucket['deductible'], 2);
            } elseif ($category->isExpense()) {
                $expense = NumberHelper::addPrecise($expense, $bucket['deductible'], 2);
                $notDeductible = NumberHelper::addPrecise($notDeductible, $rest, 2);
            } else {
                $notDeductible = NumberHelper::addPrecise($notDeductible, $bucket['gross'], 2);
            }
        }

        foreach ($unclearAccounts as $account) {
            $unclear[] = (string) __('accounting.reports.unclear.account_without_category', ['account' => $account->displayLabel()]);
        }

        $openCount = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->stillOpen()
            ->count();

        if ($openCount > 0) {
            $unclear[] = (string) __('accounting.reports.unclear.open_items', ['count' => $openCount]);
        }

        return [
            'rows' => $rows,
            'income' => $income,
            'expense' => $expense,
            'result' => NumberHelper::subtractPrecise($income, $expense, 2),
            'not_deductible' => $notDeductible,
            'unclear' => $unclear,
        ];
    }

    /**
     * Buchungen im Zeitraum, die ein Geld- oder Kassenkonto berühren.
     *
     * @return Collection<int, AccountingEntry>
     */
    private function cashEffectiveEntries(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        return AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', self::POSTED)
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->whereHas('lines.account', function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->where('is_bank', true)->orWhere('is_cash', true);
                });
            })
            ->with('lines.account')
            ->get();
    }

    /**
     * Nimmt einen Betrag in die EÜR-Kategorie seines Kontos auf; Konten ohne
     * Kategorie werden als Klärungsfall gesammelt.
     *
     * @param  array<string, array{gross: numeric-string, deductible: numeric-string}>  $buckets
     * @param  array<int, AccountingAccount>  $unclearAccounts
     * @param  numeric-string  $signed
     */
    private function collectEuer(array &$buckets, array &$unclearAccounts, AccountingAccount $account, string $signed): void {
        $category = $account->euer_category;
        if (! $category instanceof EuerCategory) {
            if (in_array($account->type, [AccountType::Income, AccountType::Expense], true)) {
                $unclearAccounts[$account->id] = $account;
            }

            return;
        }

        $key = $category->value;
        $buckets[$key] ??= ['gross' => '0.00', 'deductible' => '0.00'];
        $buckets[$key]['gross'] = NumberHelper::addPrecise($buckets[$key]['gross'], $signed, 2);
        $buckets[$key]['deductible'] = NumberHelper::addPrecise(
            $buckets[$key]['deductible'],
            $this->deductibleShare($account, $signed),
            2,
        );
    }

    /**
     * Betrag in Kontorichtung: Einnahmen stehen im Haben, Ausgaben im Soll.
     *
     * @param  numeric-string  $debitMinusCredit  Soll − Haben der Zeile bzw. des Kontos
     * @return numeric-string
     */
    private function directionalAmount(AccountingAccount $account, string $debitMinusCredit): string {
        $isIncome = $account->euer_category?->isIncome() ?? ($account->type === AccountType::Income);

        return $isIncome ? NumberHelper::negatePrecise($debitMinusCredit) : $debitMinusCredit;
    }

    /**
     * Abziehbarer Anteil nach dem Prozentsatz des Kontos. Der Faktor kommt als
     * float aus dem Modell; vier Stellen bilden jeden zweistelligen Prozentsatz
     * exakt ab, gerechnet wird dann in Dezimalarithmetik.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    private function deductibleShare(AccountingAccount $account, string $amount): string {
        return NumberHelper::multiplyPrecise($amount, Decimal::ofFloat($account->deductibleFactor(), 4)->getValue(), 2, RoundingMode::HalfUp);
    }
}
