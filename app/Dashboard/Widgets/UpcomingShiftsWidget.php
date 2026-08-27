<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpcomingShiftsWidget.php
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

/** Kommende Rufbereitschaften/Schichten des Nutzers. */
class UpcomingShiftsWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'upcoming-shifts';
    }

    public function label(): string {
        return (string) __('Nächste Schichten');
    }

    public function icon(): string {
        return 'event_upcoming';
    }

    public function defaultOrder(): int {
        return 40;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.upcoming_shifts.description');
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Overview;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.upcoming-shifts', [
            'shifts' => $this->service->upcomingShifts($user)->take(5),
        ]);
    }
}
