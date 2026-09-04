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

use App\Enums\Reselling\PeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};
use App\Support\CsvExport;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Überblick (Feature 152, MVP-765): Marge je Produkt und je Rechnungsempfänger
 * aus Soll-Einkauf, Soll-Verkauf und tatsächlich berechneten Bezügen — und
 * der Rechnungsvorschlag (MVP-764, Zwischenstand): offene Perioden je
 * Rechnungsempfänger als CSV, eine Zeile je Endkunde und Zeitraum.
 */
class ResaleReportController extends Controller {
    public function index(): View {
        $today = CarbonImmutable::today();
        $periods = $this->duePeriods($today)->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'links'])->get();

        $byProduct = [];
        $byRecipient = [];
        foreach ($periods as $period) {
            $subscription = $period->subscription;
            $billed = (float) $period->links->sum(static fn(ResalePeriodLink $l): float => $l->amount?->toFloat() ?? 0.0);
            $row = [
                'periods' => 1,
                'open' => in_array($period->status, [PeriodStatus::Open, PeriodStatus::Partial], true) ? 1 : 0,
                'expected_sale' => $period->expected_sale?->toFloat() ?? 0.0,
                'expected_purchase' => $period->expected_purchase?->toFloat() ?? 0.0,
                'billed' => $billed,
            ];
            $productKey = $subscription->productLabel() ?? $subscription->label;
            $billedTo = $subscription->billedTo();
            $recipient = $billedTo !== null ? $billedTo->name : (string) __('resale.holder.unassigned');
            foreach ([['product', $productKey], ['recipient', $recipient]] as [$kind, $key]) {
                $target = $kind === 'product' ? $byProduct : $byRecipient;
                $target[$key] ??= ['label' => $key, 'periods' => 0, 'open' => 0, 'expected_sale' => 0.0, 'expected_purchase' => 0.0, 'billed' => 0.0];
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
     * @return Builder<ResalePeriod>
     */
    private function duePeriods(CarbonImmutable $today): Builder {
        return ResalePeriod::query()
            ->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
    }
}
