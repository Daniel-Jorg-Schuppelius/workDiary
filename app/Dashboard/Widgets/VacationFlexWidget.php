<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationFlexWidget.php
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

/** Offene Urlaubsanträge und genehmigte Tage des laufenden Jahres. */
class VacationFlexWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'vacation-flex';
    }

    public function label(): string {
        return (string) __('Urlaub & Flex');
    }

    public function icon(): string {
        return 'beach_access';
    }

    public function defaultOrder(): int {
        return 140;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.vacation.description');
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.vacation-flex', [
            'vacation' => $this->service->vacation($user),
            'now' => $this->service->now(),
        ]);
    }
}
