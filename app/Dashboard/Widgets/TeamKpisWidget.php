<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamKpisWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

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

    public function availableFor(User $user): bool {
        return $user->isAdmin();
    }

    public function render(User $user): View|string {
        $data = $this->service->summarize($user);

        return view('dashboard.widgets.team-kpis', [
            'team' => $data['team'] ?? null,
        ]);
    }
}
