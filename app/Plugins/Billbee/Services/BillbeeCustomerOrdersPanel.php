<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeCustomerOrdersPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Services;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Models\BillbeeOrder;
use App\Plugins\PluginManager;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/** Billbee-Bestellungen des Kunden am Kunden-Cockpit (Feature 181, MVP-1370) — sichtbar wie die Bestellliste, also nur für Admins. */
final class BillbeeCustomerOrdersPanel implements CockpitPanel {
    private const LIMIT = 10;

    public function __construct(private readonly PluginManager $plugins) {}

    public function order(): int {
        return 81;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $viewer->isAdmin() || ! $this->plugins->enabled()->has(BillbeePlugin::ID)) {
            return null;
        }
        $query = BillbeeOrder::query()->where('organization_id', $subject->getAttribute('organization_id'))->where('customer_id', $subject->getKey());
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-billbee-orders',
            'title' => __('billbee::billbee.cockpit.title'),
            'icon' => 'shopping_bag',
            'count' => $count,
            'rows' => array_map(static function (BillbeeOrder $o): array {
                $number = $o->order_number ?? $o->billbee_order_id;

                return [
                    'title' => '#' . $number,
                    'url' => route('admin.billbee.index', ['q' => $number]),
                    'subtitle' => implode(' · ', array_filter([$o->channel, $o->total_gross?->format()])) ?: null,
                    'badge' => $o->stateLabel(),
                    'tone' => 'ghost',
                    'date' => $o->ordered_at !== null ? CarbonFmt::fdate($o->ordered_at) : null,
                ];
            }, $query->orderByDesc('ordered_at')->orderByDesc('id')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
