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

namespace App\Http\Controllers\Reselling;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reselling\{AssignResaleLineRequest, RehomeResaleSubscriptionRequest};
use App\Models\Customer\Customer;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\License\LicenseInvoiceLinks;
use App\Services\Reselling\Mirror\InvoiceMirror;
use App\Services\Reselling\Plan\{PlanLinkEditor, PlanSection};
use App\Services\Reselling\Register\{LinkProposer, PeriodLinker, RecipientReconciler};
use App\Support\{ErrorText, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Abgleich je Rechnungsempfänger (Feature 152): Perioden aller Abos eines
 * Empfängers gegen die Lizenzpositionen seiner Rechnungen — Bilanz je
 * Produkt, Kandidaten je offener Periode, Zuordnung über Abo-Grenzen hinweg.
 */
class ResaleReconcileController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly RecipientReconciler $reconciler,
        private readonly PeriodLinker $linker,
        private readonly InvoiceMirror $mirror,
        private readonly LicenseInvoiceLinks $licenseLinks,
        private readonly PlanSection $planSection,
        private readonly PlanLinkEditor $planLinks,
    ) {}

    public function index(Request $request): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $rows = $this->reconciler->overview($organization);
        $filter = (string) $request->query('show', 'problems');
        if ($filter === 'problems') {
            // Nicht fertig (Phase 165, MVP-1445) — oder ohne Kunde mit freien Positionen, die noch niemand erklärt.
            $rows = array_values(array_filter($rows, static fn(array $r): bool => ! $r['done'] || $r['proposed'] > 0 || $r['free'] > 0.001));
        }
        $totals = ['open' => 0, 'partial' => 0, 'proposed' => 0, 'free' => 0.0, 'missing' => 0.0, 'surplus' => 0.0];
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $row[$key];
            }
        }

        return view('finance.resale.reconcile', ['rows' => $rows, 'filter' => $filter, 'totals' => $totals]);
    }

    /** Nächster offener Empfänger der Arbeitsliste (Phase 165, MVP-1445) — oder die Liste, wenn alle fertig sind. */
    public function next(): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $first = $this->reconciler->worklist($organization)[0] ?? null;
        if ($first === null || $first['customer'] === null) {
            return redirect()->toList('finance.resale.reconcile.index')->with('info', __('resale.reconcile.worklist.all_done'));
        }

        return redirect()->route('finance.resale.reconcile.show', $first['customer']);
    }

    public function show(Customer $customer): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $today = ResalePeriod::today();
        $result = $this->reconciler->forCustomer($organization, $customer, $today);
        $workspace = $this->workspacePosition($organization, $customer, $today);

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

        // Stücklizenzen des Empfängers ohne Rechnungsbezug (Feature 185, MVP-1428).
        $unbilledLicenses = $this->licenseLinks->unbilled($organization, $today, (int) $customer->id);

        return view('finance.resale.reconcile_show', [
            'customer' => $customer,
            'today' => $today,
            'allPeriods' => $allPeriods,
            'targetsByProduct' => $targetsByProduct,
            'unbilledLicenses' => $unbilledLicenses,
            // Serienvorlagen des Empfängers in der Mappe (Phase 165, MVP-1444): adressiert an ihn oder über Zuordnung verbunden.
            'plan' => $this->planSection->build($organization, $result['subscriptions'], [(int) $customer->id]),
            'planCandidates' => PlanSection::candidates($result['subscriptions']),
            'workspace' => $workspace,
        ] + $result);
    }

    /**
     * Stand in der Arbeitsliste (Phase 165, MVP-1445): Position und Gesamtzahl der offenen Empfänger,
     * Vorgänger und Nachfolger; ein fertiger Empfänger steht außerhalb der Liste, „Nächster“ ist dann der erste.
     *
     * @return array{total: int, position: int|null, prev: Customer|null, next: Customer|null, done: bool, open_amount: \CommonToolkit\ValueObjects\Money|null}
     */
    private function workspacePosition(\App\Models\Platform\Organization $organization, Customer $customer, CarbonImmutable $today): array {
        $rows = $this->reconciler->worklist($organization, $today);
        $index = null;
        foreach ($rows as $i => $row) {
            if ($row['customer']?->id === $customer->id) {
                $index = $i;

                break;
            }
        }
        $row = $index !== null ? $rows[$index] : null;

        return [
            'total' => count($rows),
            'position' => $index !== null ? $index + 1 : null,
            'prev' => $index !== null && $index > 0 ? $rows[$index - 1]['customer'] : null,
            'next' => $index !== null ? ($rows[$index + 1]['customer'] ?? null) : ($rows[0]['customer'] ?? null),
            'done' => $index === null,
            'open_amount' => $row['open_amount'] ?? null,
        ];
    }

    /**
     * Vorlagenposition einem Abo des Empfängers zuordnen — dieselbe Zuordnung
     * wie im Lizenzkonto, geprüft gegen den Empfänger statt gegen das Konto.
     */
    public function planLinkStore(Request $request, Customer $customer): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $data = $request->validate([
            'subscription_id' => ['required', 'string', 'max:32'],
            'plan_key' => ['required', 'string', 'max:120'],
            'line_position' => ['required', 'integer', 'min:0', 'max:65535'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $subscriptionId = Sqid::decode(ResaleSubscription::class, (string) $data['subscription_id']);
        $subscription = $subscriptionId !== null ? ResaleSubscription::query()->with('customer', 'foreignCustomer.customer')->find($subscriptionId) : null;
        if (! $subscription instanceof ResaleSubscription || $subscription->billedTo()?->id !== $customer->id) {
            throw ValidationException::withMessages(['subscription_id' => (string) __('resale.plan.error.subscription')]);
        }
        $this->planLinks->save($organization, $subscription, (string) $data['plan_key'], (int) $data['line_position'], (int) $data['quantity'], $request->user()?->id);

        return redirect()->route('finance.resale.reconcile.show', $customer)->with('success', __('resale.plan.flash.saved'));
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
            $redirect->with('warning', ErrorText::for($e));
        }

        return $redirect;
    }

    /** Position → Periode eines beliebigen Abos dieses Empfängers. */
    public function assign(AssignResaleLineRequest $request, Customer $customer): RedirectResponse {
        $period = $request->period();
        $lines = $request->lines();
        if ($period === null || $lines === []) {
            throw ValidationException::withMessages(['line_id' => (string) __('resale.link.error.line_missing')]);
        }
        $target = route('finance.resale.reconcile.show', $customer);
        try {
            // Mehrere Zwillingszeilen (drei × „12 Monat“): die Monate laufen der Reihe nach durch die Zeilen,
            // jede nimmt, was sie noch frei hat — eine einzelne Zeile verhält sich wie bisher.
            $link = DB::transaction(function () use ($period, $lines, $request): ResalePeriodLink {
                $remaining = $request->months();
                if (count($lines) === 1) {
                    return $this->linker->attach($period, $lines[0], $remaining, $request->note(), $request->user()?->id);
                }
                $first = null;
                foreach ($lines as $line) {
                    $take = min($remaining, $this->linker->freeMonths($line, $period, true));
                    if ($take <= 0.001) {
                        continue;
                    }
                    $link = $this->linker->attach($period, $line, $take, $request->note(), $request->user()?->id);
                    $first ??= $link;
                    $remaining -= $take;
                    if ($remaining <= 0.001) {
                        break;
                    }
                }
                if ($first === null || $remaining > 0.001) {
                    // Nicht alles unterzubringen: nichts anlegen, Meldung wie bei einer einzelnen Zeile.
                    throw new \InvalidArgumentException((string) __('resale.link.error.exceeds', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label(max(0.0, $request->months() - $remaining), (float) $period->termMonths())]));
                }

                return $first;
            });
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['months' => ErrorText::for($e)]);
        }

        return redirect($target)->with('success', __('resale.link.flash.linked', ['voucher' => (string) $link->voucher_number]));
    }
}
