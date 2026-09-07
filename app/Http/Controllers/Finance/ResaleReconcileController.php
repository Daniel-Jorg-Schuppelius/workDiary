<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleReconcileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\{Customer, LexofficeVoucherLine};
use App\Models\Reselling\ResalePeriod;
use App\Services\Reselling\Register\{PeriodLinker, RecipientReconciler};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Abgleich je Rechnungsempfänger (Feature 152): Perioden aller Abos eines
 * Empfängers gegen die Lizenzpositionen seiner Rechnungen — Bilanz je
 * Produkt, Kandidaten je offener Periode, Zuordnung über Abo-Grenzen hinweg.
 */
class ResaleReconcileController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly RecipientReconciler $reconciler, private readonly PeriodLinker $linker) {}

    public function index(Request $request): View {
        $organization = $this->currentOrganizationOrNull();
        if ($organization === null) {
            abort(404);
        }
        $rows = $this->reconciler->overview($organization);
        $filter = (string) $request->query('show', 'problems');
        if ($filter === 'problems') {
            $rows = array_values(array_filter($rows, static fn(array $r): bool => $r['open'] + $r['partial'] + $r['proposed'] > 0 || $r['free'] > 0.001));
        }
        $totals = ['open' => 0, 'partial' => 0, 'proposed' => 0, 'free' => 0.0, 'missing' => 0.0, 'surplus' => 0.0];
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $row[$key];
            }
        }

        return view('finance.resale.reconcile', ['rows' => $rows, 'filter' => $filter, 'totals' => $totals]);
    }

    public function show(Customer $customer): View {
        $organization = $this->currentOrganizationOrNull();
        if ($organization === null) {
            abort(404);
        }
        $today = CarbonImmutable::today();

        return view('finance.resale.reconcile_show', ['customer' => $customer, 'today' => $today] + $this->reconciler->forCustomer($organization, $customer, $today));
    }

    /** Position → Periode eines beliebigen Abos dieses Empfängers. */
    public function assign(Request $request, Customer $customer): RedirectResponse {
        $validated = $request->validate([
            'period_id' => ['required', 'string'],
            'line_id' => ['required', 'string'],
            'months' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $periodId = Sqid::decode(ResalePeriod::class, (string) $validated['period_id']);
        $period = $periodId === null ? null : ResalePeriod::query()->with('subscription.customer', 'subscription.foreignCustomer.customer')->find($periodId);
        $lineId = Sqid::decode(LexofficeVoucherLine::class, (string) $validated['line_id']);
        $line = $lineId === null ? null : LexofficeVoucherLine::query()->with('voucher')->find($lineId);
        $target = route('finance.resale.reconcile.show', $customer);
        if ($period === null || $line === null || $period->subscription->billedTo()?->id !== $customer->id) {
            return redirect($target)->with('error', __('resale.link.error.line_missing'));
        }
        $link = $this->linker->attach($period, $line, (float) $validated['months'], $validated['note'] ?? null, $request->user()?->id);

        return redirect($target)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }
}
