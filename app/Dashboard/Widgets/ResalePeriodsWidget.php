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
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

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

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows(Permission::ResellingView->value);
    }

    public function render(User $user): View|string {
        $today = CarbonImmutable::today();
        $due = ResalePeriod::query()->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn($s) => $s->where('is_own_holding', false));
        $open = (clone $due)->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])->get(['expected_sale', 'currency']);

        return view('dashboard.widgets.resale-periods', [
            'open' => $open->count(),
            'openAmount' => (float) $open->sum(static fn(ResalePeriod $p): float => $p->expected_sale?->toFloat() ?? 0.0),
            'proposed' => (clone $due)->where('status', PeriodStatus::Billed->value)->whereNull('decided_at')->whereHas('links')->count(),
            'unassigned' => ResaleSubscription::query()->planning()->unassigned()->count(),
        ]);
    }
}
