<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyCustomerOrdersPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Services;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Models\EtsyReceipt;
use App\Plugins\PluginManager;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/** Etsy-Bestellungen des Kunden am Kunden-Cockpit (Feature 181, MVP-1370) — sichtbar wie die Bestellliste, also nur für Admins. */
final class EtsyCustomerOrdersPanel implements CockpitPanel {
    private const LIMIT = 10;

    public function __construct(private readonly PluginManager $plugins) {}

    public function order(): int {
        return 80;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $viewer->isAdmin() || ! $this->plugins->enabled()->has(EtsyPlugin::ID)) {
            return null;
        }
        $query = EtsyReceipt::query()->where('organization_id', $subject->getAttribute('organization_id'))->where('customer_id', $subject->getKey());
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-etsy-orders',
            'title' => __('etsy::etsy.cockpit.title'),
            'icon' => 'storefront',
            'count' => $count,
            'rows' => array_map(static fn (EtsyReceipt $r): array => [
                'title' => '#' . $r->receipt_id,
                'url' => route('admin.etsy.index', ['q' => (string) $r->receipt_id]),
                'subtitle' => $r->total_gross?->format(),
                'badge' => $r->status,
                'tone' => 'ghost',
                'date' => $r->ordered_at !== null ? CarbonFmt::fdate($r->ordered_at) : null,
            ], $query->orderByDesc('ordered_at')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
