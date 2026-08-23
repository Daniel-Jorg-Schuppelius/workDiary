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
use App\Services\Accounting\OpenItemService;
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
        $aging = $this->openItems->aging($organization, $direction, perPage: 50);

        return view('finance.accounting.open-items', [
            'direction' => $direction,
            'items' => $aging['items'],
            'buckets' => $aging['buckets'],
            'statuses' => OpenItemStatus::cases(),
            'canPost' => Gate::allows(Permission::AccountingLedgerPost->value),
        ]);
    }

    public function settleForm(AccountingOpenItem $item): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($item);

        return view('finance.accounting._settle_dialog', [
            'item' => $item,
            'kinds' => [SettlementKind::Discount, SettlementKind::Retention, SettlementKind::WriteOff],
        ]);
    }

    public function settle(Request $request, AccountingOpenItem $item): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($item);

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:discount,retention,write_off'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $this->openItems->settle(
            $item,
            SettlementKind::from((string) $data['kind']),
            number_format((float) $data['amount'], 2, '.', ''),
            null,
            $data['note'] ?? null,
        );

        return back()->with('status', __('accounting.open_items.flash.settled'));
    }

    private function assertSameOrganization(AccountingOpenItem $item): void {
        abort_unless((int) $item->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);
    }
}
