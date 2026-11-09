<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherPurchaseDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Billing\DocumentKind;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Services\Billing\Purchase\{PurchaseDocument, PurchaseDocumentSource, ResolvesPurchaseDocuments};
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eingehende Belege des allgemeinen Belegspiegels als Einkaufsbelege
 * (MVP-1497): Reselling-Einkauf und Materialkosten-Zuteilung für sevDesk,
 * Akaunting, Invoice Ninja und jedes weitere Programm mit Eingangsbelegen.
 * Zuteilbar ist, was {@see AccountingVoucher::scopePurchases()} als Einkauf
 * zählt (ohne Entwurf, Storno, gelöscht) und nicht archiviert ist. Netto, wo
 * der Spiegel es führt, sonst die Belegsumme. `sourceKey` ist die Plugin-ID,
 * damit Dialog und Einkaufsliste das Programm nennen.
 */
final class AccountingVoucherPurchaseDocumentSource implements PurchaseDocumentSource {
    use ResolvesPurchaseDocuments;

    private const string DOCUMENT_MODEL = AccountingVoucher::class;

    public const string KEY = 'accounting_voucher';

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new AccountingVoucher)->getMorphClass();
    }

    public function search(Organization $organization, ?string $query, ?CarbonImmutable $from, int $limit): Collection {
        $builder = $this->candidates($organization);
        if ($from !== null) {
            $builder->where('voucher_date', '>=', DateRange::day($from));
        }
        $term = trim((string) $query);
        if ($term !== '') {
            $builder->where(static fn (Builder $w) => $w->whereLikeEscaped('voucher_number', $term)
                ->orWhereHas('supplier', static fn (Builder $s) => $s->whereLikeEscaped('name', $term)));
        }

        return $this->map($builder->orderByDesc('voucher_date')->orderByDesc('id')->limit($limit)->get());
    }

    public function toDocument(AccountingVoucher $voucher, ?User $user = null): PurchaseDocument {
        $currency = CurrencyCode::tryFrom((string) $voucher->currency) ?? CurrencyCode::Euro;
        $amount = $voucher->net_amount ?? $voucher->total_amount;
        $links = app(DocumentFeedSourceRegistry::class)->voucherLinksFor((string) $voucher->plugin_id);
        $user ??= auth()->user();

        return new PurchaseDocument(
            sourceKey: (string) $voucher->plugin_id,
            morphClass: $voucher->getMorphClass(),
            morphId: (int) $voucher->id,
            key: Sqid::encode(AccountingVoucher::class, (int) $voucher->id),
            number: $voucher->voucher_number,
            date: $voucher->voucher_date !== null ? CarbonImmutable::instance($voucher->voucher_date) : null,
            vendorName: $voucher->supplier?->name,
            // Programme führen Gutschriften teils negativ; die Art sagt `credit`.
            net: $amount !== null ? Money::of((string) $amount, $currency, 2)->abs() : Money::zero($currency),
            currency: $currency,
            description: null,
            permalink: $links?->originalUrl($voucher) ?? ($user instanceof User ? $links?->originalPdfUrl($voucher, $user) : null),
            previewUrl: null,
            credit: $voucher->document_kind === DocumentKind::CreditNote->value,
        );
    }

    /** @return Builder<AccountingVoucher> */
    private function query(Organization $organization): Builder {
        return AccountingVoucher::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->with('supplier:id,name');
    }

    /** @return Builder<AccountingVoucher> */
    private function candidates(Organization $organization): Builder {
        return $this->query($organization)->purchases()->where('archived', false);
    }

    /**
     * @param  Collection<int, AccountingVoucher>  $rows
     * @return Collection<int, PurchaseDocument>
     */
    private function map(Collection $rows): Collection {
        $user = auth()->user();

        return $rows->map(fn (AccountingVoucher $voucher): PurchaseDocument => $this->toDocument($voucher, $user instanceof User ? $user : null))->values();
    }
}
