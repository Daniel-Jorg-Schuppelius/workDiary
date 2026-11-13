<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringOrderCarryForwardCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\Cockpit;

use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\{Project, RecurrenceRule};
use App\Services\CarryForward\CarryForwardCockpitList;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Support\EntityUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Offene Merker an wiederkehrenden Aufträgen eines Projekts (Feature 201,
 * MVP-1646) im Cockpit des Kunden bzw. Projekts: Reihe = Wiederholungsregel.
 * Die Merker stehen an Aufträgen; sichtbar nur mit Einsicht in alle Aufträge.
 */
final class RecurringOrderCarryForwardCockpitPanel implements CockpitPanel {
    public function __construct(private readonly CarryForwardCockpitList $list) {}

    public function order(): int {
        return 22;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer || $subject instanceof Project;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $viewer->can(Permission::DiaryViewAny->value)) {
            return null;
        }
        $rules = RecurrenceRule::query()
            ->when(
                $subject instanceof Project,
                static fn (Builder $q) => $q->where('project_id', $subject->getKey()),
                static fn (Builder $q) => $q->where(static fn (Builder $own) => $own
                    ->where('customer_id', $subject->getKey())
                    ->orWhereIn('project_id', Project::query()->where('customer_id', $subject->getKey())->select('id'))),
            )
            ->with('project:id,name,slug,customer_id')
            ->get();

        $series = [];
        foreach ($rules as $rule) {
            $key = $rule->carryForwardSeries();
            if ($key !== null) {
                $series[$key] = [
                    'label' => implode(' · ', array_filter([$rule->project?->name, $rule->name])),
                    'url' => $rule->project !== null ? EntityUrl::for($rule->project) : null,
                ];
            }
        }

        return $this->list->render((int) $subject->getAttribute('organization_id'), $series, 'cockpit-carry-forward-recurring-orders', (string) __('carry_forward.cockpit.recurring_orders'));
    }
}
