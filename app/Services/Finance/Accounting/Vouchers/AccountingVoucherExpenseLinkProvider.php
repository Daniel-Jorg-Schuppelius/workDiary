<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherExpenseLinkProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Billing\DocumentKind;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Travel\Expense;
use App\Services\Billing\Contracts\ExpenseLinkProvider;
use App\Services\Billing\ExpenseVoucherRef;
use App\Support\{MorphMap, Sqid};
use App\Support\Query\DateRange;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\{Carbon, Collection};
use RuntimeException;

/**
 * Auslage ↔ eingehender Beleg des allgemeinen Belegspiegels (MVP-1498):
 * Vorschläge und bestätigte Zuordnung wie beim Lexoffice-Anbieter, für jedes
 * Programm, dessen Belege `accounting_vouchers` führt. Die Zuordnung trägt
 * eine {@see ExternalReference} (`plugin_id` = Programm, `external_type`
 * {@see AccountingVoucher::EXPENSE_LINK}); sie macht die Auslage im Belegfluss
 * geldunwirksam (Dublettenregel 3). Einen Push gibt es nicht: Entscheidung E8
 * (Feature 106) lässt den aktiven Belegpush bei Lexoffice.
 */
final class AccountingVoucherExpenseLinkProvider implements ExpenseLinkProvider {
    /** Tage Toleranz zwischen Auslagendatum und Belegdatum im Vorschlag. */
    public const int MATCH_DAYS = 14;

    /** Cent-Toleranz beim Betragsvergleich (Rundung fremder Systeme). */
    public const int MATCH_CENTS = 2;

    public function __construct(
        private readonly string $pluginId,
        private readonly string $programLabel,
    ) {}

    public function label(): string {
        return $this->programLabel;
    }

    public function isAvailable(): bool {
        return true; // aufgelöst wird der Anbieter nur für ein aktiviertes Programm
    }

    public function link(Expense $expense, string $voucherKey): ExpenseVoucherRef {
        $voucher = $this->candidates($expense)->find(Sqid::decode(AccountingVoucher::class, $voucherKey) ?? 0);
        if (! $voucher instanceof AccountingVoucher) {
            throw new RuntimeException((string) __('expenses.receipt.voucher_unknown'));
        }
        if (in_array((string) $voucher->external_id, $this->linkedVoucherIds($expense), true)) {
            throw new RuntimeException((string) __('expenses.receipt.voucher_taken', ['number' => $voucher->voucher_number ?? '—']));
        }

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => $this->pluginId,
                'external_type' => AccountingVoucher::EXPENSE_LINK,
                'referenceable_type' => $expense->getMorphClass(),
                'referenceable_id' => $expense->getKey(),
            ],
            [
                'organization_id' => $expense->organization_id,
                'external_id' => (string) $voucher->external_id,
                'external_number' => $voucher->voucher_number,
                'synced_at' => Carbon::now(),
            ],
        );

        return $this->toRef($voucher);
    }

    public function unlink(Expense $expense): void {
        $this->referenceFor($expense)?->delete();
    }

    public function referenceFor(Expense $expense): ?ExternalReference {
        return ExternalReference::query()
            ->forPlugin((int) $expense->organization_id, $this->pluginId, AccountingVoucher::EXPENSE_LINK)
            ->forReferenceable($expense)
            ->first();
    }

    public function voucherFor(Expense $expense): ?ExpenseVoucherRef {
        $reference = $this->referenceFor($expense);
        $voucher = $reference === null ? null : AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $expense->organization_id)
            ->where('plugin_id', $this->pluginId)
            ->where('external_id', $reference->external_id)
            ->with('supplier:id,name')
            ->first();

        return $voucher instanceof AccountingVoucher ? $this->toRef($voucher) : null;
    }

    /**
     * Eingangsbelege mit gleichem Bruttobetrag (Cent-Toleranz) im Datumsfenster,
     * ohne Gutschriften und ohne Belege, die schon eine andere Auslage belegen.
     *
     * @return Collection<int, ExpenseVoucherRef>
     */
    public function suggestionsFor(Expense $expense, int $limit = 5): Collection {
        $gross = $expense->amount_gross?->toFloat();
        if ($gross === null) {
            return new Collection;
        }
        $tolerance = self::MATCH_CENTS / 100;

        /** @var Collection<int, AccountingVoucher> $candidates */
        $candidates = $this->candidates($expense)
            ->where(static fn (Builder $q) => $q->whereNull('document_kind')->orWhere('document_kind', '!=', DocumentKind::CreditNote->value))
            ->whereBetween('voucher_date', DateRange::days($expense->date->copy()->subDays(self::MATCH_DAYS), $expense->date->copy()->addDays(self::MATCH_DAYS)))
            ->whereBetween('total_amount', [$gross - $tolerance, $gross + $tolerance])
            ->whereNotIn('external_id', $this->linkedVoucherIds($expense))
            ->with('supplier:id,name')
            ->get();

        // Nächstliegendes Belegdatum zuerst; das Fenster ist eng, portables Datums-SQL bräuchte Dialektzweige.
        return $candidates
            ->sortBy(static fn (AccountingVoucher $voucher): float => abs($expense->date->diffInDays($voucher->voucher_date ?? $expense->date)))
            ->take($limit)
            ->values()
            ->map(fn (AccountingVoucher $voucher): ExpenseVoucherRef => $this->toRef($voucher));
    }

    public function canPush(Expense $expense): bool {
        return false;
    }

    public function pushVoucher(Expense $expense): ExpenseVoucherRef {
        throw new RuntimeException((string) __('expenses.receipt.push_unsupported', ['program' => $this->programLabel]));
    }

    public function wasPushed(Expense $expense): bool {
        return false;
    }

    public function pushCounterVoucher(Expense $expense, string $reason): ExpenseVoucherRef {
        throw new RuntimeException((string) __('expenses.receipt.push_unsupported', ['program' => $this->programLabel]));
    }

    public function counterVoucherFor(Expense $expense): ?ExpenseVoucherRef {
        return null;
    }

    /** @return Builder<AccountingVoucher> */
    private function candidates(Expense $expense): Builder {
        return AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $expense->organization_id)
            ->where('plugin_id', $this->pluginId)
            ->where('archived', false)
            ->purchases();
    }

    /**
     * Beleg-IDs, die schon eine ANDERE Auslage belegen — ein Beleg belegt genau eine.
     *
     * @return list<string>
     */
    private function linkedVoucherIds(Expense $expense): array {
        /** @var list<string> $ids */
        $ids = ExternalReference::query()
            ->forPlugin((int) $expense->organization_id, $this->pluginId, AccountingVoucher::EXPENSE_LINK)
            ->where('referenceable_type', MorphMap::alias(Expense::class))
            ->where('referenceable_id', '!=', $expense->getKey())
            ->pluck('external_id')
            ->all();

        return $ids;
    }

    private function toRef(AccountingVoucher $voucher): ExpenseVoucherRef {
        $currency = CurrencyCode::tryFrom((string) $voucher->currency) ?? CurrencyCode::Euro;

        return new ExpenseVoucherRef(
            externalId: (string) $voucher->external_id,
            key: Sqid::encode(AccountingVoucher::class, (int) $voucher->id),
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            gross: $voucher->total_amount !== null ? Money::of((string) $voucher->total_amount, $currency, 2) : null,
            currency: $currency,
            partyName: $voucher->supplier?->name,
        );
    }
}
