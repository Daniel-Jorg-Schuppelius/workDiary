<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Warranty\Cockpit;

use App\Enums\Warranty\WarrantyStatus;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Supplier\Supplier;
use App\Models\Warranty\WarrantyPeriod;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\Gate;

/**
 * Offene Gewährleistungsfristen (Feature 115) am Kunden, Lieferanten oder
 * Projekt — sichtbar wie die Fristenliste; am Asset die Fristen aus Aufträgen
 * und Protokollen zum Asset (MVP-1617).
 */
final class WarrantyCockpitPanel implements CockpitPanel {
    private const int LIMIT = 10;

    private const array COLUMN = [Customer::class => 'customer_id', Supplier::class => 'supplier_id', Project::class => 'project_id'];

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 40;
    }

    public function supports(Model $subject): bool {
        return isset(self::COLUMN[$subject::class]) || $subject instanceof Asset;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $this->features->routeEnabled('warranties.index') || ! Gate::forUser($viewer)->allows('viewAny', Project::class)) {
            return null;
        }
        $query = WarrantyPeriod::query()->whereIn('status', [WarrantyStatus::Open, WarrantyStatus::Claimed]);
        if ($subject instanceof Asset) {
            $query->where(fn (Builder $q) => $q
                ->whereHas('diaryEntry', fn (Builder $d) => $d->where('asset_id', $subject->getKey()))
                ->orWhereHas('protocol', fn (Builder $p) => $p->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())));
        } else {
            $query->where(self::COLUMN[$subject::class], $subject->getKey());
        }
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-warranties',
            'title' => __('cockpit.warranties.title'),
            'icon' => 'verified',
            'count' => $count,
            'rows' => array_map(static fn (WarrantyPeriod $w): array => [
                'title' => (string) ($w->trade ?: $w->side->label()),
                'url' => route('warranties.index'),
                'subtitle' => $w->trade ? $w->side->label() : null,
                'badge' => $w->status->label(),
                'tone' => $w->status->tone(),
                'date' => CarbonFmt::fdate($w->ends_on),
            ], $query->orderBy('ends_on')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
