<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\AssetCompliance\AssetComplianceAssignment;
use App\Models\User;
use App\Support\Query\DateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Fällige und überfällige Prüfungen (Prüfkalender).
 *
 * Vorfilter auf ein Fenster von 90 Tagen, damit die Kachel nicht den
 * kompletten Prüfbestand lädt; ob eine Zuordnung wirklich „bald fällig" oder
 * „überfällig" ist, entscheiden danach die Toleranzen des Modells.
 */
class AssetComplianceWidget extends Widget {
    private const WINDOW_DAYS = 90;

    public function key(): string {
        return 'asset-compliance';
    }

    public function label(): string {
        return (string) __('Fällige Prüfungen');
    }

    public function icon(): string {
        return 'fact_check';
    }

    public function defaultOrder(): int {
        return 171;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Deadlines;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.asset_compliance.description');
    }

    public function requiredModule(): ?string {
        return 'module.asset_compliance';
    }

    public function availableFor(User $user): bool {
        return parent::availableFor($user)
            && Gate::forUser($user)->allows(Permission::AssetComplianceViewAny->value);
    }

    public function render(User $user): View|string {
        $assignments = AssetComplianceAssignment::query()
            ->active()
            ->whereNotNull('next_due_on')
            ->where('next_due_on', '<', DateRange::dayAfter(now()->addDays(self::WINDOW_DAYS)))
            ->with(['asset:id,name', 'profile:id,name'])
            ->orderBy('next_due_on')
            ->limit(50)
            ->get();

        $overdue = $assignments->filter(fn ($a) => $a->isOverdue())->values();
        $dueSoon = $assignments->filter(fn ($a) => $a->isDueSoon())->values();

        return view('dashboard.widgets.asset-compliance', [
            'overdue' => $overdue,
            'dueSoon' => $dueSoon,
            'next' => $overdue->merge($dueSoon)->take(4),
        ]);
    }
}
