<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Enums\User\Permission;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

/**
 * Onboarding-Checkliste der Organisation. Verfügbar nur, solange sie nicht
 * weggeklickt wurde — die Komponente würde sonst leer rendern und eine
 * Karteileiche in der Anpassungsliste hinterlassen.
 */
class OnboardingWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'onboarding';
    }

    public function label(): string {
        return (string) __('Onboarding');
    }

    public function icon(): string {
        return 'rocket_launch';
    }

    public function defaultOrder(): int {
        return 5;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.onboarding.description');
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Overview;
    }

    public function availableFor(User $user): bool {
        if (! $user->can(Permission::OrgOnboardingView->value)) {
            return false;
        }

        $data = $this->service->onboarding($user);

        return $data !== null
            && $data['checklist'] !== []
            && empty($data['widget_dismissed_at']);
    }

    public function render(User $user): View|string {
        $data = $this->service->onboarding($user);

        return view('dashboard.widgets.onboarding', [
            'checklist' => $data['checklist'] ?? null,
            'widgetDismissedAt' => $data['widget_dismissed_at'] ?? null,
        ]);
    }
}
