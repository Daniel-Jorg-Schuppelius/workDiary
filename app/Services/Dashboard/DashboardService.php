<?php

namespace App\Services\Dashboard;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService {
    public function summarize(User $user, ?CarbonImmutable $now = null): array {
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
    private function personal(User $user, CarbonImmutable $now): array {
        $weekEnd = $now->addDays(7);

        $openEntries = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->whereIn('status', [2, 3])
            ->count();

        $progressEntries = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('status', 1)
            ->count();

        $upcomingShifts = OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        $upcomingShiftsCount = OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->count();

        $upcomingEmergencies = EmergencyAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        $upcomingEmergenciesCount = EmergencyAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->count();

        $todayShifts = OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->overlapping($now->startOfDay()->toDateTime(), $now->endOfDay()->toDateTime())
            ->orderBy('start_at')
            ->get();

        $recentEntries = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        // Letzte Kommentare auf eigenen Einträgen (auch von Anderen)
        $entryIds = DiaryEntry::query()->where('user_id', $user->id)->pluck('id');
        $recentComments = Comment::query()
            ->whereIn('diary_entry_id', $entryIds)
            ->with(['user:id,name', 'diaryEntry:id,content,user_id'])
            ->latest()
            ->limit(5)
            ->get();

        // Letzte Anhänge auf eigenen Einträgen
        $recentAttachments = Attachment::query()
            ->where('attachable_type', DiaryEntry::class)
            ->whereIn('attachable_id', $entryIds)
            ->with('uploader:id,name')
            ->latest()
            ->limit(5)
            ->get();

        return [
            'kpi' => [
                'open_entries' => $openEntries,
                'progress_entries' => $progressEntries,
                'upcoming_shifts' => $upcomingShiftsCount,
                'upcoming_emergencies' => $upcomingEmergenciesCount,
            ],
            'today_shifts' => $todayShifts,
            'upcoming_shifts' => $upcomingShifts,
            'upcoming_emergencies' => $upcomingEmergencies,
            'recent_entries' => $recentEntries,
            'recent_comments' => $recentComments,
            'recent_attachments' => $recentAttachments,
            'window_end' => $weekEnd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function team(CarbonImmutable $now): array {
        $openEntries = DiaryEntry::query()
            ->where('is_archived', false)
            ->whereIn('status', [2, 3])
            ->count();

        $progressEntries = DiaryEntry::query()
            ->where('is_archived', false)
            ->where('status', 1)
            ->count();

        $archivedToday = DiaryEntry::query()
            ->where('is_archived', true)
            ->whereBetween('archived_at', [$now->startOfDay(), $now->endOfDay()])
            ->count();

        $userCount = User::query()->count();

        $recentActivity = Comment::query()
            ->with(['user:id,name', 'diaryEntry:id,content'])
            ->latest()
            ->limit(8)
            ->get();

        return [
            'kpi' => [
                'open_entries' => $openEntries,
                'progress_entries' => $progressEntries,
                'archived_today' => $archivedToday,
                'user_count' => $userCount,
            ],
            'recent_activity' => $recentActivity,
        ];
    }
}
