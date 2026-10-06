<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteWinRateReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Sales\QuoteStatus;
use App\Models\Platform\User;
use App\Models\Sales\Quote;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Money, Percentage};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Trefferquote der Angebote (Feature 112, MVP-998). Je Angebotsnummer zählt
 * nur die jüngste Fassung. Gewonnen = (teilweise) angenommen, verloren =
 * abgelehnt, jeweils am Entscheidungstag; abgelaufen = Status „abgelaufen“
 * oder versandt mit Bindefrist vor heute, am Tag der Bindefrist. Offene
 * Angebote zählen nicht zur Quote. Volumen ist der Nettobetrag, bei
 * Teilannahme der angenommene Teil.
 */
final class QuoteWinRateReport {
    public const GROUPS = ['customer', 'owner'];

    /**
     * @return array{
     *     totals: array{won: int, lost: int, expired: int, won_volume: Money, decided_volume: Money, rate: Percentage|null, volume_rate: Percentage|null},
     *     groups: list<array{label: string, won: int, lost: int, expired: int, won_volume: Money, decided_volume: Money, rate: Percentage|null, volume_rate: Percentage|null}>,
     *     open: array{count: int, volume: Money}
     * }
     */
    public function build(int $organizationId, CarbonImmutable $from, CarbonImmutable $to, string $groupBy = 'customer'): array {
        $today = CarbonImmutable::today();
        $quotes = $this->latest($organizationId)
            ->with('customer:id,name,company')
            ->where(function (Builder $query) use ($from, $to, $today): void {
                $query->where(function (Builder $decided) use ($from, $to): void {
                    $decided->whereIn('status', [...QuoteStatus::won(), QuoteStatus::Rejected]);
                    DateRange::whereTimestampBetween($decided, 'decided_at', $from, $to);
                })->orWhere(function (Builder $expired) use ($from, $to, $today): void {
                    $expired->where(fn (Builder $q) => $q->where('status', QuoteStatus::Expired)->orWhere(fn (Builder $sent) => $sent->whereIn('status', QuoteStatus::pending())->where('valid_until', '<', DateRange::day($today))))
                        ->whereBetween('valid_until', DateRange::days($from, $to));
                });
            })
            ->get();

        $owners = User::query()->whereIn('id', $quotes->map(fn (Quote $quote): ?int => $quote->follow_up_user_id ?? $quote->created_by)->filter()->unique()->values())
            ->pluck('name', 'id');
        $groups = $quotes->groupBy(fn (Quote $quote): string => $groupBy === 'owner'
            ? (string) ($owners[$quote->follow_up_user_id ?? $quote->created_by] ?? __('quotes.win_rate.no_owner'))
            : (string) ($quote->customer?->company ?: ($quote->customer->name ?? '—')));

        $open = $this->latest($organizationId)->whereIn('status', QuoteStatus::open())
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($today)))
            ->get();

        return [
            'totals' => $this->figures($quotes),
            'groups' => array_values($groups->map(fn (Collection $rows, string $label): array => ['label' => $label, ...$this->figures($rows)])
                ->sortByDesc(fn (array $row): int => $row['won'] + $row['lost'] + $row['expired'])->all()),
            'open' => ['count' => $open->count(), 'volume' => $this->volume($open)],
        ];
    }

    /** @return Builder<Quote> */
    private function latest(int $organizationId): Builder {
        return Quote::query()->where('organization_id', $organizationId)
            ->whereNotExists(fn (QueryBuilder $newer) => $newer->selectRaw('1')->from('quotes as newer')->whereColumn('newer.previous_version_id', 'quotes.id'));
    }

    /**
     * @param  Collection<int, Quote>  $quotes
     * @return array{won: int, lost: int, expired: int, won_volume: Money, decided_volume: Money, rate: Percentage|null, volume_rate: Percentage|null}
     */
    private function figures(Collection $quotes): array {
        $won = $quotes->filter(fn (Quote $quote): bool => $quote->status->isWon());
        $lost = $quotes->where('status', QuoteStatus::Rejected);
        $decided = $quotes->count();
        $wonVolume = $this->volume($won);
        $decidedVolume = $this->volume($quotes);

        return [
            'won' => $won->count(),
            'lost' => $lost->count(),
            'expired' => $decided - $won->count() - $lost->count(),
            'won_volume' => $wonVolume,
            'decided_volume' => $decidedVolume,
            'rate' => $decided === 0 ? null : Percentage::fromRatio(Decimal::of($won->count()), Decimal::of($decided), 1),
            'volume_rate' => $decidedVolume->isPositive() ? Percentage::fromRatio(Decimal::of($wonVolume->getAmount()), Decimal::of($decidedVolume->getAmount()), 1) : null,
        ];
    }

    /** @param  Collection<int, Quote>  $quotes */
    private function volume(Collection $quotes): Money {
        return Money::sum($quotes->map(fn (Quote $quote): Money => $quote->subtotal ?? Money::zero(CurrencyCode::Euro))->all(), CurrencyCode::Euro);
    }
}
