<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonalKpisWidget.php
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

/** KPI-Zeile des Nutzers (vormals fest über den Tabs). */
class PersonalKpisWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'personal-kpis';
    }

    public function label(): string {
        return (string) __('Persönliche Kennzahlen');
    }

    public function icon(): string {
        return 'person';
    }

    public function defaultOrder(): int {
        return 10;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.personal_kpis.description');
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Overview;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.personal-kpis', [
            'kpi' => $this->service->personalKpis($user),
        ]);
    }
}
