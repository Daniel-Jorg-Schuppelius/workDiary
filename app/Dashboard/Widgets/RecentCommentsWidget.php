<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecentCommentsWidget.php
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

/** Neue Kommentare auf den eigenen Einträgen. */
class RecentCommentsWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'recent-comments';
    }

    public function label(): string {
        return (string) __('Neue Kommentare auf meinen Einträgen');
    }

    public function icon(): string {
        return 'comment';
    }

    public function defaultOrder(): int {
        return 110;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.recent_comments.description');
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Activity;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.recent-comments', [
            'comments' => $this->service->recentComments($user),
        ]);
    }
}
