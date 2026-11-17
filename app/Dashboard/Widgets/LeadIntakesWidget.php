<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakesWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\Sales\LeadIntakeStage;
use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Sales\LeadIntake;
use Illuminate\Contracts\View\View;

/**
 * Kachel „Aufnahmen“ (Feature 200, MVP-1641): offene Aufnahmeakten je Stufe
 * (Anfrage, Gespräch, Unterlagen, Entscheidung, Einrichtung) mit Sprung in die
 * gefilterte Übersicht. Nur im Vertriebsmodul und mit Sicht auf Leads.
 */
class LeadIntakesWidget extends Widget {
    public function key(): string {
        return 'lead-intakes';
    }

    public function label(): string {
        return (string) __('lead_intake.widget.title');
    }

    public function icon(): string {
        return 'assignment_ind';
    }

    public function defaultOrder(): int {
        return 74;
    }

    /**
     * Nur, wo die Organisation Aufnahmen führt: ein Prüfpaket ist eingeschaltet
     * oder es gibt schon eine Akte — sonst bleibt die Kachel weg.
     */
    public function availableFor(User $user): bool {
        if (! parent::availableFor($user)) {
            return false;
        }
        $organizationId = \App\Support\OrganizationContext::currentId() ?? (int) $user->organization_id;
        $settings = (array) (\App\Models\Platform\Organization::query()->find($organizationId)->settings ?? []);

        return ! empty($settings['lead_intake']['packages'] ?? null)
            || LeadIntake::query()->where('organization_id', $organizationId)->exists();
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function description(): ?string {
        return (string) __('lead_intake.widget.description');
    }

    public function requiredModule(): ?string {
        return 'module.vertrieb';
    }

    public function requiredAbility(): ?string {
        return Permission::CustomerViewAny->value;
    }

    public function render(User $user): View|string {
        $organizationId = \App\Support\OrganizationContext::currentId() ?? (int) $user->organization_id;
        $counts = LeadIntake::query()
            ->where('organization_id', $organizationId)
            ->whereNull('superseded_at')
            ->whereIn('stage', LeadIntakeStage::openValues())
            ->selectRaw('stage, COUNT(*) AS n')
            ->groupBy('stage')
            ->pluck('n', 'stage');

        return view('dashboard.widgets.lead-intakes', [
            'stages' => LeadIntakeStage::pipeline(),
            'counts' => $counts,
            'total' => (int) $counts->sum(),
        ]);
    }
}
