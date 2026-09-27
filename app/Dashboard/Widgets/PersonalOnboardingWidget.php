<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonalOnboardingWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Models\Platform\User;
use App\Services\Onboarding\PersonalOnboardingResolver;
use Illuminate\Contracts\View\View;

/** Persönlicher Einstieg (MVP-911): sichtbar, bis alles erledigt oder weggeklickt ist. */
class PersonalOnboardingWidget extends Widget {
    public function __construct(private readonly PersonalOnboardingResolver $onboarding) {}

    public function key(): string {
        return 'personal-onboarding';
    }

    public function label(): string {
        return (string) __('onboarding.personal.title');
    }

    public function icon(): string {
        return 'flag';
    }

    public function defaultOrder(): int {
        return 6;
    }

    public function description(): ?string {
        return (string) __('onboarding.personal.description');
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Overview;
    }

    public function availableFor(User $user): bool {
        $checklist = $this->onboarding->forUser($user);

        return ! $checklist['dismissed'] && $checklist['total'] > 0 && $checklist['done'] < $checklist['total'];
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.personal-onboarding', ['checklist' => $this->onboarding->forUser($user)]);
    }
}
