<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodayShiftsWidget.php
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

/** Schichten des laufenden Tages. */
class TodayShiftsWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'today-shifts';
    }

    public function label(): string {
        return (string) __('Heute');
    }

    public function icon(): string {
        return 'today';
    }

    public function defaultOrder(): int {
        return 30;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.today_shifts.description');
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Overview;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.today-shifts', [
            'shifts' => $this->service->todayShifts($user),
        ]);
    }
}
