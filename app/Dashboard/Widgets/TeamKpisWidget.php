<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamKpisWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

/** Team-KPIs; nur für Admins, wie schon im alten Tab „Überblick". */
class TeamKpisWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'team-kpis';
    }

    public function label(): string {
        return (string) __('Team-Kennzahlen');
    }

    public function icon(): string {
        return 'groups';
    }

    public function defaultOrder(): int {
        return 90;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.team_kpis.description');
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Overview;
    }

    public function availableFor(User $user): bool {
        return $user->isAdmin();
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.team-kpis', [
            'kpi' => $this->service->teamKpis(),
        ]);
    }
}
