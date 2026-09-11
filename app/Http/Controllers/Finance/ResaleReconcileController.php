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
use App\Http\Requests\Finance\Resale\{AssignResaleLineRequest, RehomeResaleSubscriptionRequest};
use App\Models\Customer;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Mirror\InvoiceMirror;
use App\Services\Reselling\Register\{LinkProposer, PeriodLinker, RecipientReconciler};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Abgleich je Rechnungsempfänger (Feature 152): Perioden aller Abos eines
 * Empfängers gegen die Lizenzpositionen seiner Rechnungen — Bilanz je
 * Produkt, Kandidaten je offener Periode, Zuordnung über Abo-Grenzen hinweg.
 */
class ResaleReconcileController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly RecipientReconciler $reconciler, private readonly PeriodLinker $linker, private readonly InvoiceMirror $mirror) {}

    public function index(Request $request): View {
        $organization = $this->currentOrganizationOrAbort(404);
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
        $organization = $this->currentOrganizationOrAbort(404);
        $today = ResalePeriod::today();
        $result = $this->reconciler->forCustomer($organization, $customer, $today);

        // Alle Perioden des Empfängers (auch gedeckte), chronologisch je Abo.
        $allPeriods = [];
        $links = [];
        foreach ($result['subscriptions'] as $subscription) {
            foreach ($subscription->periods as $period) {
                $allPeriods[] = ['period' => $period, 'subscription' => $subscription];
                foreach ($period->links as $link) {
                    $links[] = $link;
                }
            }
        }
        $this->mirror->preload($organization, $links);
        usort($allPeriods, static fn(array $a, array $b): int => strcmp($a['period']->starts_on->toDateString(), $b['period']->starts_on->toDateString()) ?: ($a['subscription']->id <=> $b['subscription']->id));

        // Ziele der Zuordnung je Produkt: gleiches Produkt zuerst, die übrigen als Gruppe.
        $targetsByProduct = [];
        foreach ($result['periods'] as $row) {
            $targetsByProduct[$row['product']][] = $row;
        }

        return view('finance.resale.reconcile_show', [
            'customer' => $customer,
            'today' => $today,
            'allPeriods' => $allPeriods,
            'targetsByProduct' => $targetsByProduct,
        ] + $result);
    }

    /**
     * Halter eines Abos auf einen anderen Kunden setzen — die Rechnung ging
     * nachweislich dorthin (Anbieter-Konto ≠ Kunde). Danach Vorschlagslauf,
     * damit die Positionen des neuen Empfängers sofort greifen.
     */
    public function rehome(RehomeResaleSubscriptionRequest $request, Customer $customer, LinkProposer $proposer): RedirectResponse {
        $period = $request->period();
        if ($period === null) {
            throw ValidationException::withMessages(['period_id' => (string) __('resale.link_error.period_missing')]);
        }
        $target = $request->target();
        /** @var ResaleSubscription $subscription */
        $subscription = $period->subscription;
        $previous = ['customer_id' => $subscription->customer_id, 'foreign_customer_id' => $subscription->foreign_customer_id, 'is_own_holding' => $subscription->is_own_holding];
        $subscription->forceFill(['customer_id' => $target->id, 'foreign_customer_id' => null, 'is_own_holding' => false])->save();
        $subscription->audit('resale_subscription.rehomed', ['from' => $previous, 'to' => ['customer_id' => $target->id], 'period_id' => $period->id]);
        $redirect = redirect(route('finance.resale.reconcile.show', $target))->with('success', __('resale.reconcile.flash.rehomed', ['subscription' => $subscription->label, 'customer' => $target->name]));
        try {
            $proposer->propose($this->currentOrganizationOrAbort(404));
        } catch (RuntimeException $e) {
            // Lauf läuft schon: der Halterwechsel ist gespeichert, die Vorschläge kommen mit dem nächsten Lauf.
            $redirect->with('warning', $e->getMessage());
        }

        return $redirect;
    }

    /** Position → Periode eines beliebigen Abos dieses Empfängers. */
    public function assign(AssignResaleLineRequest $request, Customer $customer): RedirectResponse {
        $period = $request->period();
        $line = $request->line();
        if ($period === null || $line === null) {
            throw ValidationException::withMessages(['line_id' => (string) __('resale.link.error.line_missing')]);
        }
        $target = route('finance.resale.reconcile.show', $customer);
        try {
            $link = $this->linker->attach($period, $line, $request->months(), $request->note(), $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['months' => $e->getMessage()]);
        }

        return redirect($target)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }
}
