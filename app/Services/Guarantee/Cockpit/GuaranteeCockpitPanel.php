<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Guarantee\Cockpit;

use App\Enums\Guarantee\GuaranteeStatus;
use App\Models\Customer\Customer;
use App\Models\Guarantee\Guarantee;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Supplier\Supplier;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/** Laufende Bürgschaften (Feature 114) am Kunden, Lieferanten oder Projekt — sichtbar mit Abrechnungsrecht wie das Register. */
final class GuaranteeCockpitPanel implements CockpitPanel {
    private const LIMIT = 10;

    private const COLUMN = [Customer::class => 'customer_id', Supplier::class => 'supplier_id', Project::class => 'project_id'];

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 45;
    }

    public function supports(Model $subject): bool {
        return isset(self::COLUMN[$subject::class]);
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $this->features->routeEnabled('guarantees.index') || ! $viewer->canManageBilling()) {
            return null;
        }
        $query = Guarantee::query()
            ->where(self::COLUMN[$subject::class], $subject->getKey())
            ->where('status', GuaranteeStatus::Active);
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-guarantees',
            'title' => __('cockpit.guarantees.title'),
            'icon' => 'shield',
            'count' => $count,
            'rows' => array_map(static fn (Guarantee $g): array => [
                'title' => implode(' · ', array_filter([$g->kind->label(), $g->reference])),
                'url' => route('guarantees.index'),
                'subtitle' => $g->direction->label() . ' · ' . $g->amount->format(),
                'badge' => $g->status->label(),
                'tone' => $g->status->tone(),
                'date' => $g->expires_on !== null ? CarbonFmt::fdate($g->expires_on) : null,
            ], $query->orderBy('expires_on')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
