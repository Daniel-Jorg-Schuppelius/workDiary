<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AcceptedQuotesWithoutInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Chain;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Sales\QuoteStatus;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Quote;
use App\Services\Billing\Contracts\DocumentChainSource;
use App\Services\Billing\Dto\DocumentChainItem;
use App\Support\CarbonFmt;
use Illuminate\Database\Eloquent\Builder;

/** Belegkette (MVP-1057): angenommene Angebote, zu denen noch keine Rechnung besteht. */
final class AcceptedQuotesWithoutInvoice implements DocumentChainSource {
    public function key(): string {
        return 'quotes_to_invoice';
    }

    public function label(): string {
        return (string) __('invoicing.chain.quotes_to_invoice');
    }

    public function icon(): string {
        return 'request_quote';
    }

    public function routeName(): string {
        return 'quotes.show';
    }

    public function availableFor(User $user): bool {
        return $user->can('viewAny', Quote::class);
    }

    public function count(Organization $organization): int {
        return $this->query($organization)->count();
    }

    public function items(Organization $organization, int $limit): array {
        return array_values($this->query($organization)->with('customer:id,name,company')->orderBy('decided_at')->limit($limit)->get()
            ->map(fn (Quote $quote): DocumentChainItem => new DocumentChainItem(
                title: $quote->number . ' · ' . ($quote->customer?->displayLabel() ?? '—'),
                detail: (string) __('invoicing.chain.accepted_on', ['date' => ($quote->decided_at !== null ? CarbonFmt::fdate($quote->decided_at) : '—')]),
                url: route('quotes.show', $quote),
                amount: $quote->subtotal,
                date: $quote->decided_at,
            ))->all());
    }

    /** @return Builder<Quote> */
    private function query(Organization $organization): Builder {
        return Quote::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', QuoteStatus::won())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('invoices')
                ->whereColumn('invoices.quote_id', 'quotes.id')
                ->where('invoices.status', '!=', InvoiceStatus::Cancelled));
    }
}
