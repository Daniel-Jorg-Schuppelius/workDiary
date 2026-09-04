<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Reselling\{BillingFrequency, PeriodStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResalePriceEntry, ResaleSubscription};
use App\Services\Reselling\Marketplace\ProductNameMatcher;
use App\Services\Reselling\Register\ResaleInvoiceDraftService;
use App\Support\{CsvExport, Sqid};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Überblick (Feature 152, MVP-765): Marge je Produkt und je Rechnungsempfänger
 * aus Soll-Einkauf, Soll-Verkauf und tatsächlich berechneten Bezügen — und
 * der Rechnungsvorschlag (MVP-764, Zwischenstand): offene Perioden je
 * Rechnungsempfänger als CSV, eine Zeile je Endkunde und Zeitraum.
 */
class ResaleReportController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        $today = CarbonImmutable::today();
        $periods = $this->duePeriods($today)->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'links', 'purchases'])->get();

        $byProduct = [];
        $byRecipient = [];
        foreach ($periods as $period) {
            $subscription = $period->subscription;
            $billed = (float) $period->links->sum(static fn(ResalePeriodLink $l): float => $l->amount?->toFloat() ?? 0.0);
            $actual = $period->actualPurchase();
            $row = [
                'periods' => 1,
                'open' => in_array($period->status, [PeriodStatus::Open, PeriodStatus::Partial], true) ? 1 : 0,
                'expected_sale' => $period->expected_sale?->toFloat() ?? 0.0,
                'expected_purchase' => $period->expected_purchase?->toFloat() ?? 0.0,
                'actual_purchase' => $actual ?? 0.0,
                'with_actual' => $actual === null ? 0 : 1,
                'billed' => $billed,
            ];
            $productKey = $subscription->productLabel() ?? $subscription->label;
            $billedTo = $subscription->billedTo();
            $recipient = $billedTo !== null ? $billedTo->name : (string) __('resale.holder.unassigned');
            foreach ([['product', $productKey], ['recipient', $recipient]] as [$kind, $key]) {
                $target = $kind === 'product' ? $byProduct : $byRecipient;
                $target[$key] ??= ['label' => $key, 'periods' => 0, 'open' => 0, 'expected_sale' => 0.0, 'expected_purchase' => 0.0, 'actual_purchase' => 0.0, 'with_actual' => 0, 'billed' => 0.0];
                foreach ($row as $field => $value) {
                    $target[$key][$field] += $value;
                }
                if ($kind === 'product') {
                    $byProduct = $target;
                } else {
                    $byRecipient = $target;
                }
            }
        }
        $sort = static function (array &$rows): void {
            usort($rows, static fn(array $a, array $b): int => $b['expected_sale'] <=> $a['expected_sale']);
        };
        $sort($byProduct);
        $sort($byRecipient);

        return view('finance.resale.report', [
            'byProduct' => $byProduct,
            'byRecipient' => $byRecipient,
            'today' => $today,
        ]);
    }

    /** Offene Perioden als Rechnungsvorschlag (CSV, eine Zeile je Periode). */
    public function export(): StreamedResponse {
        $today = CarbonImmutable::today();
        $periods = $this->duePeriods($today)
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'subscription.article', 'subscription.lexofficeArticle', 'links'])
            ->get()
            ->sortBy([static fn(ResalePeriod $a, ResalePeriod $b): int => strcmp((string) $a->subscription->billedTo()?->name, (string) $b->subscription->billedTo()?->name) ?: $a->starts_on <=> $b->starts_on]);

        $header = [
            (string) __('resale.field.billed_to'), (string) __('resale.field.holder'), (string) __('resale.field.label'), (string) __('resale.field.article'),
            (string) __('resale.field.period'), (string) __('resale.field.quantity'), (string) __('resale.link.months'), (string) __('resale.export.open_months'),
            (string) __('resale.field.sale_unit_price'), (string) __('resale.export.open_amount'), (string) __('resale.field.status'),
        ];
        $rows = [];
        foreach ($periods as $period) {
            $subscription = $period->subscription;
            $required = $period->requiredMonths();
            $openMonths = max(0.0, $required - $period->coveredMonths());
            $sale = $period->expected_sale?->toFloat() ?? 0.0;
            $rows[] = [
                $subscription->billedTo()?->name,
                $subscription->holderLabel(),
                $subscription->label,
                $subscription->productLabel() ?? '',
                $period->label(),
                $period->quantity,
                number_format($required, 2, ',', ''),
                number_format($openMonths, 2, ',', ''),
                $subscription->sale_unit_price !== null ? number_format($subscription->sale_unit_price->toFloat(), 2, ',', '') : '',
                number_format($required > 0 ? $sale * $openMonths / $required : 0.0, 2, ',', ''),
                $period->status->label(),
            ];
        }

        return CsvExport::streamFromRows('abo-rechnungsvorschlag-' . $today->format('Y-m-d') . '.csv', $header, $rows);
    }

    /**
     * Preisprüfung (aus Feature 151 übernommen): je Produkt Einkauf laut
     * Vertrag, aktueller Katalogpreis und UVP gegen die Verkaufspreise.
     */
    public function prices(): View {
        $today = CarbonImmutable::today();
        $subscriptions = ResaleSubscription::query()->planning()->where('is_own_holding', false)->with('lexofficeArticle')->get();
        $catalog = ResalePriceEntry::query()->validOn($today)->where('term_months', 12)->where('interval', BillingFrequency::Yearly->value)->get();
        $matcher = new ProductNameMatcher;
        $rows = [];
        foreach ($subscriptions->groupBy(static fn(ResaleSubscription $s): string => $s->lexoffice_article_id !== null ? 'a' . $s->lexoffice_article_id : 'n' . ProductNameMatcher::normalize($s->label)) as $group) {
            /** @var ResaleSubscription $first */
            $first = $group->first();
            $label = $first->lexofficeArticle !== null ? $first->lexofficeArticle->name : $first->label;
            $purchases = $group->map(static fn(ResaleSubscription $s): ?float => $s->purchase_unit_price?->toFloat())->filter()->values();
            $sales = $group->map(static fn(ResaleSubscription $s): ?float => $s->sale_unit_price?->toFloat())->filter()->sort()->values();
            $entry = $catalog->first(static fn(ResalePriceEntry $e): bool => ProductNameMatcher::normalize($e->product) === ProductNameMatcher::normalize($label))
                ?? $catalog->first(static fn(ResalePriceEntry $e): bool => $matcher->matches($label, $e->product));
            $listPrice = $entry?->purchase_unit_price->toFloat();
            $uvp = $entry?->list_unit_price?->toFloat();
            $median = $sales->isEmpty() ? null : (float) $sales->get(intdiv($sales->count(), 2));
            $flags = [];
            if ($median !== null && $purchases->isNotEmpty() && $median < (float) $purchases->max()) {
                $flags[] = 'below_purchase';
            }
            if ($median !== null && $uvp !== null && $median < $uvp) {
                $flags[] = 'below_list';
            }
            if ($listPrice !== null && $purchases->isNotEmpty() && (float) $purchases->max() > $listPrice + 0.01) {
                $flags[] = 'contract_above_catalog';
            }
            if ($sales->isEmpty()) {
                $flags[] = 'no_sales';
            }
            $rows[] = [
                'label' => $label,
                'subscriptions' => $group->count(),
                'quantity' => (int) $group->sum('quantity'),
                'purchase_min' => $purchases->isEmpty() ? null : (float) $purchases->min(),
                'purchase_max' => $purchases->isEmpty() ? null : (float) $purchases->max(),
                'list_price' => $listPrice,
                'uvp' => $uvp,
                'sale_min' => $sales->isEmpty() ? null : (float) $sales->first(),
                'sale_median' => $median,
                'sale_max' => $sales->isEmpty() ? null : (float) $sales->last(),
                'margin' => $median !== null && $purchases->isNotEmpty() ? $median - (float) $purchases->max() : null,
                'flags' => $flags,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => count($b['flags']) <=> count($a['flags']) ?: strcmp($a['label'], $b['label']));

        return view('finance.resale.prices', ['rows' => $rows, 'today' => $today, 'catalogDate' => $catalog->max('valid_from')]);
    }

    public function draftCreate(ResaleInvoiceDraftService $drafts): View {
        $today = CarbonImmutable::today();
        $recipients = [];
        $periods = $this->duePeriods($today)->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'links'])
            ->get();
        foreach ($periods as $period) {
            $recipient = $period->subscription->billedTo();
            if ($recipient === null) {
                continue;
            }
            $recipients[$recipient->id] ??= ['customer' => $recipient, 'periods' => 0, 'net' => 0.0];
            $recipients[$recipient->id]['periods']++;
            $required = $period->requiredMonths();
            $openMonths = max(0.0, $required - $period->coveredMonths());
            $recipients[$recipient->id]['net'] += $required > 0 ? ($period->expected_sale?->toFloat() ?? 0.0) * $openMonths / $required : 0.0;
        }
        usort($recipients, static fn(array $a, array $b): int => strcmp($a['customer']->name, $b['customer']->name));

        return view('finance.resale._draft_dialog', ['recipients' => $recipients]);
    }

    public function draftStore(Request $request, ResaleInvoiceDraftService $drafts): RedirectResponse {
        $validated = $request->validate(['customer_id' => ['required', 'string']]);
        $id = Sqid::decode(Customer::class, (string) $validated['customer_id']);
        $recipient = $id === null ? null : Customer::query()->find($id);
        $organization = $this->currentOrganizationOrNull();
        if ($recipient === null || $organization === null) {
            return back()->withErrors(['customer_id' => __('resale.error.customer_required')]);
        }
        try {
            $result = $drafts->draft($organization, $recipient, $request->user());
        } catch (\Throwable $e) {
            return redirect()->route('finance.resale.periods.index')->with('error', $e->getMessage());
        }

        $key = $result['local'] ? 'resale.draft.flash.created_local' : 'resale.draft.flash.created';

        return redirect()->route('finance.resale.periods.index')->with('success', __($key, ['customer' => $recipient->name, 'lines' => $result['lines'], 'net' => number_format($result['net'], 2, ',', '.'), 'id' => $result['draft_id']]));
    }

    /**
     * @return Builder<ResalePeriod>
     */
    private function duePeriods(CarbonImmutable $today): Builder {
        return ResalePeriod::query()
            ->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
    }
}
