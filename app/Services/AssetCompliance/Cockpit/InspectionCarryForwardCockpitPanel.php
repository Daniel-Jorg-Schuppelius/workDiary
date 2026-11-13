<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionCarryForwardCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance\Cockpit;

use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceProfile};
use App\Models\Platform\User;
use App\Services\CarryForward\CarryForwardCockpitList;
use App\Services\Cockpit\Contracts\CockpitPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Merker an den Prüfpflichten eines Assets (Feature 201, MVP-1646):
 * Reihe = Prüfpflicht, Vorkommen = Prüftermin bzw. Position eines Prüfauftrags.
 */
final class InspectionCarryForwardCockpitPanel implements CockpitPanel {
    public function __construct(private readonly CarryForwardCockpitList $list) {}

    public function order(): int {
        return 36;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Asset;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Asset || ! Gate::forUser($viewer)->allows('viewAny', AssetComplianceProfile::class)) {
            return null;
        }
        $series = [];
        $assignments = AssetComplianceAssignment::query()->where('asset_id', $subject->id)->with('profile:id,name')->get();
        foreach ($assignments as $assignment) {
            $series[$assignment->carryForwardSeries()] = [
                'label' => (string) ($assignment->profile->name ?? __('carry_forward.cockpit.inspections')),
                'url' => route('asset-compliance.schedules.index'),
            ];
        }

        return $this->list->render((int) $subject->organization_id, $series, 'cockpit-carry-forward-inspections', (string) __('carry_forward.cockpit.inspections'));
    }
}
