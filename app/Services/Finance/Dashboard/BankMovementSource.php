<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankMovementSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Dashboard;

use App\Enums\Finance\{MatchStatus, TransactionDirection};
use App\Models\Finance\{BankStatement, BankTransaction};
use App\Models\Platform\{Organization, User};
use App\Services\Dashboard\Contracts\FinanceMovementSource;
use App\Services\Dashboard\Dto\{FinanceMovement, FinanceMovementSummary};
use App\Services\Licensing\FeatureFlagResolver;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\Gate;

/** Umsätze gebuchter Kontoauszüge (Zahlungsabgleich, Feature 045) in der Kachel „Finanzbewegungen“ (MVP-1617). */
final class BankMovementSource implements FinanceMovementSource {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'bank';
    }

    public function label(): string {
        return (string) __('dashboard.widget.finance_movements.source.bank');
    }

    public function icon(): string {
        return 'account_balance';
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled('finance.reconciliation.index') && Gate::forUser($viewer)->allows('viewAny', BankStatement::class);
    }

    public function summary(Organization $organization, CarbonImmutable $since, int $latest): FinanceMovementSummary {
        $open = [MatchStatus::Unmatched->value, MatchStatus::Suggested->value];
        $base = BankTransaction::query()->where('organization_id', $organization->id)->booked();

        // Gezählt wie „offen“ auf der Seite Zahlungsabgleich.
        return new FinanceMovementSummary(
            unassigned: (clone $base)->whereIn('match_status', $open)->count(),
            url: route('finance.reconciliation.index'),
            unassignedUrl: null,
            latest: array_values(array_map(static function (BankTransaction $t) use ($open): FinanceMovement {
                $amount = Money::of((string) $t->amount, $t->currency);

                return new FinanceMovement(
                    bookedOn: CarbonImmutable::instance($t->booking_date),
                    title: (string) ($t->counterparty_name ?: __('dashboard.widget.finance_movements.no_counterparty')),
                    amount: $t->direction === TransactionDirection::Debit ? $amount->abs()->negated() : $amount->abs(),
                    assigned: ! in_array($t->match_status->value, $open, true),
                );
            }, (clone $base)->where('booking_date', '>=', $since->toDateString())->orderByDesc('booking_date')->orderByDesc('id')->limit($latest)->get()->all())),
        );
    }
}
