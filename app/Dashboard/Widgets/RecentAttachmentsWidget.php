<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecentAttachmentsWidget.php
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

/** Neue Anhänge auf den eigenen Einträgen. */
class RecentAttachmentsWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'recent-attachments';
    }

    public function label(): string {
        return (string) __('Neue Anhänge auf meinen Einträgen');
    }

    public function icon(): string {
        return 'attach_file';
    }

    public function defaultOrder(): int {
        return 120;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.recent_attachments.description');
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Activity;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.recent-attachments', [
            'attachments' => $this->service->recentAttachments($user),
        ]);
    }
}
