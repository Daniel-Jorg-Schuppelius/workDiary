<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTasksWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\User\Permission;
use App\Models\{OperationsTask, User};
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Dashboard-Kachel des Admin-Aufgabencenters (Feature 041/MVP-058,
 * nachgezogen als B3/MVP-344): Anzahl offener Betriebsaufgaben der
 * eigenen Organisation plus die drei dringendsten Einträge. Sichtbar
 * nur mit `platform.operations.view` (gleiches Gate wie Menüpunkt und
 * OperationsTaskController); Sortierung wie das Aufgabencenter
 * (critical → warning → zuletzt gesehen).
 */
class OperationsTasksWidget extends Widget {
    public function key(): string {
        return 'operations-tasks';
    }

    public function label(): string {
        return (string) __('operations.title.widget');
    }

    public function icon(): string {
        return 'task_alt';
    }

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows(Permission::PlatformOperationsView->value);
    }

    public function render(User $user): View|string {
        $query = OperationsTask::query()
            ->where('organization_id', (int) $user->organization_id)
            ->active()
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->orderByDesc('last_seen_at');

        return view('dashboard.widgets.operations-tasks', [
            'openCount' => (clone $query)->count(),
            'tasks' => $query->limit(3)->get(),
        ]);
    }
}
