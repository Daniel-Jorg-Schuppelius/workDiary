<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenItemController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{OpenItemDirection, OpenItemStatus, SettlementKind};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingOpenItem;
use App\Services\Accounting\{DiscountTaxSplit, OpenItemService};
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Offene Posten (Feature 125, MVP-674): Debitoren und Kreditoren mit
 * Altersstruktur.
 *
 * Manuelle Ausgleiche sind auf die Fälle beschränkt, die kein Zahlungsvorgang
 * abbildet — Skonto, Einbehalt, Ausbuchung. Geldflüsse kommen aus dem
 * Zahlungsabgleich, nicht aus diesem Formular.
 */
class OpenItemController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly OpenItemService $openItems) {}

    public function index(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $direction = OpenItemDirection::tryFrom((string) $request->query('direction', ''))
            ?? OpenItemDirection::Receivable;

        // Seitenweise: Bei mehreren tausend Posten kostet das Hydrieren der
        // Modelle mehr als die Abfrage (MVP-683).
        // Statusfilter der Liste (MVP-1559): offen, teilweise ausgeglichen, strittig.
        $status = OpenItemStatus::tryFrom((string) $request->query('status', ''));
        $status = $status?->isOpen() === true ? $status : null;
        $aging = $this->openItems->aging($organization, $direction, perPage: 50, status: $status);

        return view('finance.accounting.open-items', [
            'direction' => $direction,
            'items' => $aging['items'],
            // Wartende Gegenbuchungen (E9): der Posten zeigt sie, statt einen
            // zweiten Ausgleich anzubieten.
            'pendingDrafts' => $this->openItems->pendingDrafts($aging['items'] instanceof LengthAwarePaginator ? $aging['items']->items() : $aging['items']),
            'buckets' => $aging['buckets'],
            'statuses' => array_values(array_filter(OpenItemStatus::cases(), static fn (OpenItemStatus $case): bool => $case->isOpen())),
            'selectedStatus' => $status,
            'canPost' => Gate::allows(Permission::AccountingLedgerPost->value),
        ]);
    }

    public function settleForm(AccountingOpenItem $item): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($item);

        return view('finance.accounting._settle_dialog', [
            'item' => $item,
            'kinds' => [SettlementKind::Discount, SettlementKind::Retention, SettlementKind::WriteOff],
            'missingCounterAccounts' => $this->openItems->missingCounterAccounts($this->currentOrganizationOrAbort(), $item->direction),
            // Steuersätze, nach denen ein Skonto berichtigt wird (§ 17 UStG); leer = ohne Steuerbezug.
            'discountTaxRates' => app(DiscountTaxSplit::class)->rates($item),
            'discountSelfAssessed' => app(DiscountTaxSplit::class)->selfAssessed($item),
        ]);
    }

    public function settle(Request $request, AccountingOpenItem $item): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($item);

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:discount,retention,write_off'],
            'amount' => ['required', 'numeric', 'decimal:0,8', 'gt:0'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $settlement = $this->openItems->settle(
            $item,
            SettlementKind::from((string) $data['kind']),
            Decimal::of((string) $data['amount'], 2)->getValue(),
            null,
            $data['note'] ?? null,
        );

        return back()->with('status', $settlement === null
            ? __('accounting.open_items.flash.awaiting_approval')
            : __('accounting.open_items.flash.settled'));
    }

    /** Dialog: Posten als strittig kennzeichnen (MVP-1559), Grund ist Pflicht. */
    public function disputeForm(AccountingOpenItem $item): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($item);

        return view('finance.accounting._dispute_dialog', ['item' => $item]);
    }

    public function dispute(Request $request, AccountingOpenItem $item): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($item);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $this->openItems->markDisputed($item, (string) $data['reason'], $this->authUser());

        return back()->with('status', __('accounting.dispute.flash.marked'));
    }

    public function withdrawDispute(AccountingOpenItem $item): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($item);

        $this->openItems->withdrawDispute($item, $this->authUser());

        return back()->with('status', __('accounting.dispute.flash.withdrawn'));
    }
}
