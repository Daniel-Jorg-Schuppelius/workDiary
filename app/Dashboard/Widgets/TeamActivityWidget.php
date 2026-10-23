<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamActivityWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Services\Timeline\ActivityFeedService;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Letzte Aktivität im Team (Feature 183): die jüngsten Einträge des
 * Aktivitäts-Feeds — mit dem Recht des Audit-Protokolls wie die Seite.
 */
class TeamActivityWidget extends Widget {
    private const DAYS = 14;

    public function __construct(private readonly ActivityFeedService $feed) {}

    public function key(): string {
        return 'team-activity';
    }

    public function label(): string {
        return (string) __('Letzte Team-Aktivität');
    }

    public function icon(): string {
        return 'groups';
    }

    public function defaultOrder(): int {
        return 100;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.team_activity.description');
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Activity;
    }

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows('viewAny', AuditLog::class);
    }

    public function render(User $user): View|string {
        $now = CarbonImmutable::now();

        return view('dashboard.widgets.team-activity', [
            'items' => $this->feed->global((int) (OrganizationContext::currentId() ?? $user->organization_id), $now->subDays(self::DAYS), $now, limit: 8)['items'],
        ]);
    }
}
