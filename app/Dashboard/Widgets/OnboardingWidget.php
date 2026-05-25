<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\User\Permission;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

class OnboardingWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'onboarding';
    }

    public function label(): string {
        return (string) __('Onboarding');
    }

    public function icon(): string {
        return 'task_alt';
    }

    public function requiredAbility(): ?string {
        return Permission::OrgOnboardingView->value;
    }

    public function render(User $user): View|string {
        $data = $this->service->summarize($user);

        return view('dashboard.widgets.onboarding', [
            'onboarding' => $data['onboarding'] ?? null,
        ]);
    }
}
