<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EarlyWarningsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Services\Reporting\EarlyWarningService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Kachel „Auffälligkeiten“ (MVP-889): Frühwarnungen mit Handlungsempfehlung. */
class EarlyWarningsWidget extends Widget {
    public function key(): string {
        return 'early-warnings';
    }

    public function label(): string {
        return (string) __('reporting.warning.widget.title');
    }

    public function icon(): string {
        return 'crisis_alert';
    }

    public function defaultOrder(): int {
        return 178;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Operations;
    }

    public function description(): ?string {
        return (string) __('reporting.warning.widget.description');
    }

    public function availableFor(User $user): bool {
        return parent::availableFor($user)
            && Gate::forUser($user)->allows(Permission::ReportView->value);
    }

    public function render(User $user): View|string {
        $organization = Organization::query()->find($user->organization_id);

        return view('dashboard.widgets.early-warnings', [
            'warnings' => $organization !== null ? app(EarlyWarningService::class)->cached($organization) : [],
        ]);
    }
}
