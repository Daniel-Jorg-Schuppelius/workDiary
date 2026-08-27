<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenItemsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\Finance\OpenItemStatus;
use App\Enums\User\Permission;
use App\Models\Accounting\AccountingOpenItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Offene Posten: Summe und überfälliger Anteil, je Richtung. */
class OpenItemsWidget extends Widget {
    public function key(): string {
        return 'open-items';
    }

    public function label(): string {
        return (string) __('Offene Posten');
    }

    public function icon(): string {
        return 'account_balance';
    }

    public function defaultOrder(): int {
        return 132;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.open_items.description');
    }

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows(Permission::AccountingLedgerView->value);
    }

    public function render(User $user): View|string {
        $open = [OpenItemStatus::Open->value, OpenItemStatus::PartiallySettled->value];

        $rows = AccountingOpenItem::query()
            ->whereIn('status', $open)
            ->selectRaw('direction, SUM(open_amount) as total, SUM(CASE WHEN due_date < ? THEN open_amount ELSE 0 END) as overdue', [now()->toDateString()])
            ->groupBy('direction')
            ->get();

        return view('dashboard.widgets.open-items', [
            'rows' => $rows,
        ]);
    }
}
