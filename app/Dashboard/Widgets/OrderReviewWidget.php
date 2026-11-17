<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderReviewWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\Diary\Status;
use App\Enums\User\Permission;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Diary\Review\ReviewQueue;
use Illuminate\Contracts\View\View;

/**
 * Kachel „Zur Prüfung“ (Feature 198, MVP-1636): Aufträge, die der Nutzer
 * prüfen darf, in der Reihenfolge der Prüf-Warteschlange (Fälligkeit, dann
 * Vorlagedatum). Nur für Prüfende: Recht `order.review` oder als prüfende
 * Person eines offenen Auftrags benannt.
 */
class OrderReviewWidget extends Widget {
    private const int LIMIT = 5;

    public function __construct(private readonly ReviewQueue $queue) {}

    public function key(): string {
        return 'order-review';
    }

    public function label(): string {
        return (string) __('diary.review.widget.title');
    }

    public function icon(): string {
        return 'fact_check';
    }

    public function defaultOrder(): int {
        return 73;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function description(): ?string {
        return (string) __('diary.review.widget.description');
    }

    public function availableFor(User $user): bool {
        if (! parent::availableFor($user)) {
            return false;
        }

        return $user->can(Permission::OrderReview->value)
            || DiaryEntry::query()->where('reviewer_user_id', $user->id)->where('status', Status::InReview->value)->exists();
    }

    public function render(User $user): View|string {
        $organizationId = \App\Support\OrganizationContext::currentId() ?? (int) $user->organization_id;
        $query = $this->queue->for($user, $organizationId);

        return view('dashboard.widgets.order-review', [
            'total' => $this->queue->count($user, $organizationId),
            'entries' => $query->with(['customer:id,name', 'entryType:id,label', 'reviewSubmitter:id,name'])->limit(self::LIMIT)->get(),
        ]);
    }
}
