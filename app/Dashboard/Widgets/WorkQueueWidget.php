<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueueWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Models\Platform\User;
use App\Services\WorkQueue\WorkQueue;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/** Die dringendsten Vorgänge des Arbeitsvorrats (Feature 179, MVP-1363). */
class WorkQueueWidget extends Widget {
    private const LIMIT = 6;

    public function __construct(private readonly WorkQueue $queue) {}

    public function key(): string {
        return 'work-queue';
    }

    public function label(): string {
        return (string) __('work-queue.widget.label');
    }

    public function icon(): string {
        return 'task_alt';
    }

    public function defaultOrder(): int {
        return 8;
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Overview;
    }

    public function description(): ?string {
        return (string) __('work-queue.widget.description');
    }

    public function render(User $user): View|string {
        $organization = OrganizationContext::current();
        $now = CarbonImmutable::now();
        $sources = $this->queue->sourcesFor($user);
        $items = $organization !== null ? $this->queue->itemsFor($user, $organization, $sources, $now) : [];

        return view('dashboard.widgets.work-queue', [
            'items' => array_slice($items, 0, self::LIMIT),
            'total' => count($items),
            'sources' => $sources,
            'now' => $now,
        ]);
    }
}
