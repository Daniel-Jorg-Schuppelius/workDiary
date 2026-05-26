<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecentEmergenciesWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

class RecentEmergenciesWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'recent-emergencies';
    }

    public function label(): string {
        return (string) __('Aktuelle Notdienste');
    }

    public function icon(): string {
        return 'emergency';
    }

    public function render(User $user): View|string {
        $data = $this->service->summarize($user);
        $personal = $data['user'] ?? [];

        return view('dashboard.widgets.recent-emergencies', [
            'emergencies' => $personal['upcoming_emergencies'] ?? collect(),
        ]);
    }
}
