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
use App\Enums\Finance\{OpenItemDirection, OpenItemStatus};
use App\Enums\User\Permission;
use App\Models\Accounting\AccountingOpenItem;
use App\Models\Platform\User;
use App\Services\Billing\Contracts\{ExternalPurchases, ExternalRevenue};
use App\Services\Billing\Dto\ExternalInvoiceQuery;
use App\Services\Navigation\NavigationRegistry;
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
        $organization = $user->organization;
        if ($organization !== null && ! app(NavigationRegistry::class)->condition('accounting.local_ledger', $user, $organization)) {
            return $this->renderExternal((int) $organization->id);
        }

        $open = [OpenItemStatus::Open->value, OpenItemStatus::PartiallySettled->value];

        $rows = AccountingOpenItem::query()
            ->whereIn('status', $open)
            ->selectRaw('direction, SUM(open_amount) as total, SUM(CASE WHEN due_date < ? THEN open_amount ELSE 0 END) as overdue', [now()->toDateString()])
            ->groupBy('direction')
            ->get();

        return view('dashboard.widgets.open-items', [
            'rows' => $rows,
            'external' => false,
        ]);
    }

    /**
     * Ohne lokale Buchhaltung (MVP-1125): offene Forderungen und
     * Verbindlichkeiten aus dem Buchhaltungsprogramm, Link auf den Belegfluss.
     */
    private function renderExternal(int $organizationId): View {
        $today = now()->toDateString();
        $receivable = ['open' => 0.0, 'overdue' => 0.0];
        foreach (app(ExternalRevenue::class)->invoices(new ExternalInvoiceQuery(withCreditNotes: false, openOnly: true, organizationId: $organizationId)) as $invoice) {
            $receivable['open'] += $invoice->open;
            if ($invoice->dueOn !== null && $invoice->dueOn->toDateString() < $today) {
                $receivable['overdue'] += $invoice->open;
            }
        }
        $payable = app(ExternalPurchases::class)->openPayables($organizationId, $today);

        $rows = collect([
            (object) ['direction' => OpenItemDirection::Receivable, 'total' => $receivable['open'], 'overdue' => $receivable['overdue']],
            (object) ['direction' => OpenItemDirection::Payable, 'total' => $payable['open'], 'overdue' => $payable['overdue']],
        ])->filter(static fn (object $row): bool => $row->total > 0)->values();

        return view('dashboard.widgets.open-items', [
            'rows' => $rows,
            'external' => true,
        ]);
    }
}
