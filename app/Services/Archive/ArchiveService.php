<?php

namespace App\Services\Archive;

use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use Carbon\CarbonImmutable;

class ArchiveService {
    /**
     * Run the archive sweep using the configured threshold.
     *
     * @return array{diary:int,shifts:int,assignments:int,total:int,cutoff:string}
     */
    public function run(?int $thresholdDays = null, ?CarbonImmutable $now = null): array {
        $days = $thresholdDays ?? (int) config('archive.threshold_days', 30);
        $now = $now ?? CarbonImmutable::now();
        $cutoff = $now->subDays($days);

        $diary = DiaryEntry::query()
            ->where('is_archived', false)
            ->where('status', -1)
            ->where('updated_at', '<', $cutoff)
            ->update(['is_archived' => true, 'archived_at' => $now]);

        $shifts = OnCallShift::query()
            ->where('is_archived', false)
            ->whereNotNull('end_at')
            ->where('end_at', '<', $cutoff)
            ->update(['is_archived' => true]);

        $assignments = EmergencyAssignment::query()
            ->where('is_archived', false)
            ->whereNotNull('end_at')
            ->where('end_at', '<', $cutoff)
            ->update(['is_archived' => true]);

        return [
            'diary' => (int) $diary,
            'shifts' => (int) $shifts,
            'assignments' => (int) $assignments,
            'total' => (int) ($diary + $shifts + $assignments),
            'cutoff' => $cutoff->toDateTimeString(),
        ];
    }

    public function archiveEntry(DiaryEntry $entry, ?CarbonImmutable $now = null): void {
        $entry->forceFill([
            'is_archived' => true,
            'archived_at' => $now ?? CarbonImmutable::now(),
        ])->save();
    }

    public function restoreEntry(DiaryEntry $entry): void {
        $entry->forceFill([
            'is_archived' => false,
            'archived_at' => null,
        ])->save();
    }
}
