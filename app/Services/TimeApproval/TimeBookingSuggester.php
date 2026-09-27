<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeBookingSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval;

use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Projektvorschlag je offenem Zeitblock (MVP-923): zuerst ein eigener Auftrag
 * mit Projekt, der zeitlich überlappt, dann ein Auftrag des Tages (auch aus
 * der Tour), zuletzt das zuletzt gebuchte Projekt. Nur ein Vorschlag — gebucht
 * wird erst durch die Person.
 */
final class TimeBookingSuggester {
    public const SOURCE_ORDER = 'order';

    public const SOURCE_DAY = 'day';

    public const SOURCE_RECENT = 'recent';

    /**
     * @param  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable, minutes: int}>  $blocks
     * @param  Collection<int, Project>  $projects  wählbare Projekte (Vorschlag nur aus dieser Menge)
     * @param  Collection<int, int>  $recentProjectIds
     * @return list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable, minutes: int, project: ?Project, source: ?string, hint: ?string}>
     */
    public function suggest(User $user, CarbonImmutable $day, array $blocks, Collection $projects, Collection $recentProjectIds): array {
        $byId = $projects->keyBy('id');
        $orders = DiaryEntry::query()
            ->whereNotNull('project_id')
            ->whereIn('project_id', $byId->keys())
            ->where(fn ($q) => $q->where('assigned_user_id', $user->id)->orWhere(fn ($o) => $o->whereNull('assigned_user_id')->where('user_id', $user->id)))
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('start_at', '<', DateRange::dayAfter($day))->where('end_at', '>', DateRange::dayStart($day)))
                ->orWhereBetween('scheduled_for', DateRange::days($day, $day)))
            ->orderBy('start_at')
            ->get(['id', 'project_id', 'title', 'start_at', 'end_at', 'tour_position']);
        $recent = $recentProjectIds->map(static fn (mixed $id): int => (int) $id)->first(fn (int $id): bool => $byId->has($id));

        $out = [];
        foreach ($blocks as $block) {
            $overlap = $orders->first(fn (DiaryEntry $e): bool => $e->start_at !== null && $e->end_at !== null
                && $e->start_at->lessThan($block['ended_at']) && $e->end_at->greaterThan($block['started_at']));
            $dayOrder = $overlap ?? $orders->first();
            [$project, $source, $hint] = match (true) {
                $overlap !== null => [$byId->get($overlap->project_id), self::SOURCE_ORDER, $overlap->title],
                $dayOrder !== null => [$byId->get($dayOrder->project_id), self::SOURCE_DAY, $dayOrder->title],
                $recent !== null => [$byId->get($recent), self::SOURCE_RECENT, null],
                default => [null, null, null],
            };
            $out[] = $block + ['project' => $project, 'source' => $source, 'hint' => $hint];
        }

        return $out;
    }
}
