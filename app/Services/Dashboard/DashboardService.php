<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Dashboard;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\ScheduledShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService
{
    /** @return array<string, mixed> */
    public function summarize(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'now' => $now,
            'user' => $this->personal($user, $now),
            'team' => $user->isAdmin() ? $this->team($now) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personal(User $user, CarbonImmutable $now): array
    {
        $weekEnd = $now->addDays(7);

        // Einzel-Query statt 2× COUNT für Diary-Einträge
        /** @var object{open_cnt: int|string, progress_cnt: int|string}|null $entryCounts */
        $entryCounts = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->selectRaw('SUM(status IN (2,3)) as open_cnt, SUM(status = 1) as progress_cnt')
            ->first();

        // Upcoming Shifts: Einzel-Query, Count über ->count() der Collection
        $upcomingShifts = OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->get();

        // Upcoming Emergencies: ebenso
        $upcomingEmergencies = EmergencyAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->get();

        $todayShifts = OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->overlapping($now->startOfDay()->toDateTime(), $now->endOfDay()->toDateTime())
            ->orderBy('start_at')
            ->get();

        $recentEntries = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->select(['id', 'user_id', 'content', 'status', 'start_at', 'updated_at'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        // Subquery statt pluck() + large IN-Klausel
        $userEntryIds = DiaryEntry::query()->where('user_id', $user->id)->select('id');

        $recentComments = Comment::query()
            ->whereIn('diary_entry_id', $userEntryIds)
            ->with(['user:id,name', 'diaryEntry:id,content,user_id'])
            ->latest()
            ->limit(5)
            ->get();

        $recentAttachments = Attachment::query()
            ->where('attachable_type', DiaryEntry::class)
            ->whereIn('attachable_id', $userEntryIds)
            ->with('uploader:id,name')
            ->latest()
            ->limit(5)
            ->get();

        $upcomingScheduledShifts = ScheduledShift::query()
            ->where('user_id', $user->id)
            ->forDateRange($now->toDateString(), $now->addDays(7)->toDateString())
            ->visible()
            ->with('shiftType:id,name,abbreviation,color')
            ->orderBy('date')
            ->limit(7)
            ->get();

        return [
            'kpi' => [
                'open_entries' => (int) $entryCounts?->open_cnt,
                'progress_entries' => (int) $entryCounts?->progress_cnt,
                'upcoming_shifts' => $upcomingShifts->count(),
                'upcoming_emergencies' => $upcomingEmergencies->count(),
            ],
            'today_shifts' => $todayShifts,
            'upcoming_shifts' => $upcomingShifts->take(5),
            'upcoming_emergencies' => $upcomingEmergencies->take(5),
            'recent_entries' => $recentEntries,
            'recent_comments' => $recentComments,
            'recent_attachments' => $recentAttachments,
            'upcoming_scheduled' => $upcomingScheduledShifts,
            'window_end' => $weekEnd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function team(CarbonImmutable $now): array
    {
        // Einzel-Query statt 2× COUNT
        /** @var object{open_cnt: int|string, progress_cnt: int|string}|null $entryCounts */
        $entryCounts = DiaryEntry::query()
            ->where('is_archived', false)
            ->selectRaw('SUM(status IN (2,3)) as open_cnt, SUM(status = 1) as progress_cnt')
            ->first();

        $archivedToday = DiaryEntry::query()
            ->where('is_archived', true)
            ->whereBetween('archived_at', [$now->startOfDay(), $now->endOfDay()])
            ->count();

        $userCount = User::query()->count();

        $recentActivity = Comment::query()
            ->select(['id', 'user_id', 'diary_entry_id', 'body', 'created_at'])
            ->with(['user:id,name', 'diaryEntry:id,content'])
            ->latest()
            ->limit(8)
            ->get();

        return [
            'kpi' => [
                'open_entries' => (int) $entryCounts?->open_cnt,
                'progress_entries' => (int) $entryCounts?->progress_cnt,
                'archived_today' => $archivedToday,
                'user_count' => $userCount,
            ],
            'recent_activity' => $recentActivity,
        ];
    }
}
