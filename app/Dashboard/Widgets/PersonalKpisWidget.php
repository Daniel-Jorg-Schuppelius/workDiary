<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonalKpisWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

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

    public function render(User $user): View|string {
        $data = $this->service->summarize($user);

        return view('dashboard.widgets.personal-kpis', [
            'user' => $user,
            'personal' => $data['user'] ?? [],
        ]);
    }
}
