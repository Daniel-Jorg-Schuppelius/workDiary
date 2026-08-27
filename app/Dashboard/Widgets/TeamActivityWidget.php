<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamActivityWidget.php
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

/** Letzte Kommentare im Team; nur für Admins (wie der alte Tab-Block). */
class TeamActivityWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'team-activity';
    }

    public function label(): string {
        return (string) __('Letzte Team-Aktivität');
    }

    public function icon(): string {
        return 'groups';
    }

    public function defaultOrder(): int {
        return 100;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.team_activity.description');
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Activity;
    }

    public function availableFor(User $user): bool {
        return $user->isAdmin();
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.team-activity', [
            'comments' => $this->service->teamActivity(),
        ]);
    }
}
