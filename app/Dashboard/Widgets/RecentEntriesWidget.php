<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecentEntriesWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

/** Zuletzt bearbeitete eigene Auftragsbuch-Einträge. */
class RecentEntriesWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'recent-entries';
    }

    public function label(): string {
        return (string) __('Meine letzten Einträge');
    }

    public function icon(): string {
        return 'history_edu';
    }

    public function defaultOrder(): int {
        return 80;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.recent_entries.description');
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.recent-entries', [
            'entries' => $this->service->recentEntries($user),
        ]);
    }
}
