<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierScorecardCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Cockpit;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Procurement\SupplierScorecardService;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Leistung des Lieferanten im Lieferanten-360° (MVP-1617): die Scorecard der
 * letzten zwölf Monate, gerechnet wie die Detail-Scorecard, auf die jede Zeile verlinkt.
 */
final class SupplierScorecardCockpitPanel implements CockpitPanel {
    private const int MONTHS = 12;

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly SupplierScorecardService $scorecards,
    ) {}

    public function order(): int {
        return 14;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Supplier;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Supplier
            || ! $this->features->routeEnabled('supplier-scorecards.show')
            || ! ($viewer->isAdmin() || Gate::forUser($viewer)->allows(Permission::InventoryViewAny->value))) {
            return null;
        }
        $to = CarbonImmutable::today()->endOfDay();
        $from = $to->subMonthsNoOverflow(self::MONTHS)->addDay()->startOfDay();
        $card = $this->scorecards->scorecard($subject, $from, $to, withSeries: false);
        $url = route('supplier-scorecards.show', ['supplier' => $subject, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $percent = static fn (?float $rate): string => $rate === null ? '—' : NumberHelper::toGermanFormat($rate * 100, 0) . ' %';

        $metrics = [
            ['scorecard.metric_ontime', $card['ontime']['available'], fn (): string => $percent($card['ontime']['rate']), $card['ontime']['goodness'],
                fn (): string => (string) __('scorecard.ontime_detail', ['on' => $card['ontime']['on_time'], 'total' => $card['ontime']['evaluated']])],
            ['scorecard.metric_complaints', $card['complaints']['available'], fn (): string => $percent($card['complaints']['rate']), $card['complaints']['goodness'],
                fn (): string => (string) __('scorecard.complaints_detail', ['count' => $card['complaints']['count'], 'base' => $card['complaints']['base']])],
            ['scorecard.metric_price', $card['price']['available'], fn (): string => ($card['price']['trend_pct'] > 0 ? '+' : '') . NumberHelper::toGermanFormat((float) $card['price']['trend_pct'], 1) . ' %', $card['price']['goodness'],
                fn (): string => (string) __('scorecard.price_dir_' . ($card['price']['direction'] ?? 'none'))],
            ['scorecard.metric_quality', $card['quality']['available'], fn (): string => $card['quality']['rating']->label(), $card['quality']['goodness'], fn (): ?string => null],
            ['scorecard.metric_recourse', $card['recourse']['available'], fn (): string => $percent($card['recourse']['acceptance_rate']), $card['recourse']['goodness'],
                fn (): string => (string) __('scorecard.recourse_detail', ['answered' => $card['recourse']['answered'], 'submitted' => $card['recourse']['submitted']])],
            ['scorecard.metric_lead_time', $card['lead_time']['available'], fn (): string => (string) __('scorecard.days', ['days' => NumberHelper::toGermanFormat((float) $card['lead_time']['median'], 1)]), null, fn (): ?string => null],
        ];
        $rows = [];
        foreach ($metrics as [$label, $available, $value, $goodness, $detail]) {
            if (! $available) {
                continue;
            }
            $rows[] = [
                'title' => (string) __($label),
                'url' => $url,
                'subtitle' => $detail(),
                'badge' => $value(),
                'tone' => match (true) {
                    $goodness === null => 'ghost',
                    $goodness >= 80 => 'success',
                    $goodness >= 50 => 'warning',
                    default => 'error',
                },
                'date' => null,
            ];
        }
        if ($rows === []) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-supplier-scorecard',
            'title' => __('cockpit.supplier_scorecard.title'),
            'icon' => 'leaderboard',
            'count' => count($rows),
            'summary' => $card['overall'] !== null
                ? __('cockpit.supplier_scorecard.summary', ['score' => $card['overall'], 'months' => self::MONTHS])
                : __('cockpit.supplier_scorecard.summary_none', ['months' => self::MONTHS]),
            'rows' => $rows,
        ]);
    }
}
