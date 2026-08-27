<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenTimesWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\{TimeEntry, User};
use App\Support\Query\DateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Noch nicht abgerechnete, abrechenbare Zeiten — dieselbe Grundmenge wie die
 * Arbeitsliste „Offene Zeiten": nicht exportiert und ohne Kunden, deren
 * Abrechnung ein Fremdsystem führt.
 */
class OpenTimesWidget extends Widget {
    /** Ab wann eine offene Zeit als Altbestand gilt (wie in der Arbeitsliste). */
    private const STALE_AFTER_DAYS = 45;

    public function key(): string {
        return 'open-times';
    }

    public function label(): string {
        return (string) __('Offene Zeiten');
    }

    public function icon(): string {
        return 'hourglass_bottom';
    }

    public function defaultOrder(): int {
        return 131;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.open_times.description');
    }

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows(Permission::TimeEntryViewAny->value);
    }

    public function render(User $user): View|string {
        $query = TimeEntry::query()
            ->withoutLedgerManagedCustomers()
            ->where('time_entries.exported', false)
            ->where('time_entries.billable', true);

        /** @var object{cnt:int|string, mins:int|string|null} $totals */
        $totals = (clone $query)->selectRaw('COUNT(*) as cnt, SUM(minutes) as mins')->first();

        return view('dashboard.widgets.open-times', [
            'count' => (int) $totals->cnt,
            'minutes' => (int) ($totals->mins ?? 0),
            'staleCount' => (clone $query)
                ->where('time_entries.date', '<', DateRange::day(now()->subDays(self::STALE_AFTER_DAYS)))
                ->count(),
            'staleAfterDays' => self::STALE_AFTER_DAYS,
        ]);
    }
}
