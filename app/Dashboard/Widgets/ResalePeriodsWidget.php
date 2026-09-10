<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriodsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\Reselling\PeriodStatus;
use App\Enums\User\Permission;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Models\User;
use App\Support\Query\DateRange;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/** Reselling-Register (Feature 152, MVP-765): offene Abrechnungsperioden, unbestätigte Vorschläge, Abos ohne Halter. */
class ResalePeriodsWidget extends Widget {
    public function key(): string {
        return 'resale-periods';
    }

    public function label(): string {
        return (string) __('resale.widget.title');
    }

    public function icon(): string {
        return 'subscriptions';
    }

    public function defaultOrder(): int {
        return 136;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function description(): ?string {
        return (string) __('resale.widget.description');
    }

    public function requiredModule(): ?string {
        return 'module.reselling';
    }

    public function requiredAbility(): ?string {
        return Permission::ResellingView->value;
    }

    public function render(User $user): View|string {
        $today = ResalePeriod::today();
        $due = ResalePeriod::query()->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
        $open = (clone $due)->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])->with('links')->get();

        // Offener Betrag: Teilperioden nur anteilig (openAmount), je Währung summiert.
        /** @var array<string, Money> $amounts */
        $amounts = [];
        foreach ($open as $period) {
            $amount = $period->openAmount();
            if ($amount === null) {
                continue;
            }
            $code = $amount->getCurrency()->value;
            $amounts[$code] = isset($amounts[$code]) ? $amounts[$code]->plus($amount) : $amount;
        }

        return view('dashboard.widgets.resale-periods', [
            'open' => $open->count(),
            'openAmount' => implode(' · ', array_map(static fn(Money $m): string => $m->withScale(2)->format(), array_values($amounts))),
            'proposed' => (clone $due)->where('status', PeriodStatus::Billed->value)->whereNull('decided_at')->whereHas('links')->count(),
            'unassigned' => ResaleSubscription::query()->planning()->unassigned()->count(),
        ]);
    }
}
