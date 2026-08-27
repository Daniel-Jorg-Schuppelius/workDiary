<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssuesWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

/** Offene Punkte, die dem Nutzer zugewiesen sind (nach Fälligkeit). */
class OpenIssuesWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'open-issues';
    }

    public function label(): string {
        return (string) __('Meine offenen Punkte');
    }

    public function icon(): string {
        return 'flag';
    }

    public function defaultOrder(): int {
        return 70;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.open_issues.description');
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.open-issues', [
            'issues' => $this->service->openIssuesAssigned($user),
            'kpi' => $this->service->personalKpis($user),
        ]);
    }
}
