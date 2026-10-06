<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuotesToFollowUp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Chain;

use App\Enums\Sales\QuoteStatus;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Quote;
use App\Services\Billing\Contracts\DocumentChainSource;
use App\Services\Billing\Dto\DocumentChainItem;
use App\Support\CarbonFmt;
use Illuminate\Database\Eloquent\Builder;

/** Belegkette (MVP-1057): versandte Angebote mit fälliger Wiedervorlage oder abgelaufener Bindefrist. */
final class QuotesToFollowUp implements DocumentChainSource {
    public function key(): string {
        return 'quotes_follow_up';
    }

    public function label(): string {
        return (string) __('invoicing.chain.quotes_follow_up');
    }

    public function icon(): string {
        return 'phone_callback';
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
        return array_values($this->query($organization)->with('customer:id,name,company')->orderByRaw('COALESCE(follow_up_at, valid_until)')->limit($limit)->get()
            ->map(fn (Quote $quote): DocumentChainItem => new DocumentChainItem(
                title: $quote->number . ' · ' . ($quote->customer?->displayLabel() ?? '—'),
                detail: $quote->isExpired()
                    ? (string) __('invoicing.chain.expired_on', ['date' => ($quote->valid_until !== null ? CarbonFmt::fdate($quote->valid_until) : '—')])
                    : (string) __('invoicing.chain.follow_up_on', ['date' => ($quote->follow_up_at !== null ? CarbonFmt::fdate($quote->follow_up_at) : '—')]),
                url: route('quotes.show', $quote),
                amount: $quote->subtotal,
                date: $quote->follow_up_at ?? $quote->valid_until,
            ))->all());
    }

    /** @return Builder<Quote> */
    private function query(Organization $organization): Builder {
        $today = now()->toDateString();

        return Quote::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', QuoteStatus::pending())
            ->where(fn ($q) => $q
                ->where(fn ($f) => $f->whereNotNull('follow_up_at')->whereNull('followed_up_at')->where('follow_up_at', '<=', $today))
                ->orWhere(fn ($e) => $e->whereNotNull('valid_until')->where('valid_until', '<', $today)));
    }
}
