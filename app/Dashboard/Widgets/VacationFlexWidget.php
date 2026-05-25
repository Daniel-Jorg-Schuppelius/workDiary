<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationFlexWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

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

    public function render(User $user): View|string {
        $data = $this->service->summarize($user);
        $vacation = $data['finance']['vacation'] ?? [];

        return view('dashboard.widgets.vacation-flex', [
            'vacation' => $vacation,
        ]);
    }
}
