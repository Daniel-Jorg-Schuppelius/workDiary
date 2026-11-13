<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReviewQueue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Review;

use App\Enums\Diary\Status;
use App\Enums\User\Permission;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Prüf-Warteschlange (Feature 198, MVP-1636): Aufträge zur Prüfung, die der
 * Nutzer prüfen darf — als benannte prüfende Person oder mit `order.review`
 * bei Aufträgen ohne benannte Person. Eigene Vorlagen erscheinen nie.
 * Reihenfolge: Fälligkeit (ohne zuletzt), dann Vorlagedatum.
 */
final class ReviewQueue {
    /** @return Builder<DiaryEntry> */
    public function for(User $viewer, int $organizationId): Builder {
        $mayReviewAll = $viewer->can(Permission::OrderReview->value);

        return DiaryEntry::query()
            ->where('organization_id', $organizationId)
            ->where('status', Status::InReview->value)
            ->where('is_archived', false)
            ->where(static function (Builder $q) use ($viewer): void {
                $q->whereNull('review_submitter_user_id')->orWhere('review_submitter_user_id', '!=', $viewer->id);
            })
            ->where(static function (Builder $q) use ($viewer, $mayReviewAll): void {
                $q->where('reviewer_user_id', $viewer->id);
                if ($mayReviewAll) {
                    $q->orWhereNull('reviewer_user_id');
                }
            })
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('review_submitted_at')
            ->orderBy('id');
    }

    public function count(User $viewer, int $organizationId): int {
        return $this->for($viewer, $organizationId)->reorder()->count();
    }
}
