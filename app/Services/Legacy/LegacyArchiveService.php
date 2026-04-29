<?php

namespace App\Services\Legacy;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyArchiveService {
    public function archiveOlderThanMonths(int $months, ?int $legacyUserId = null): array {
        $cutoff = now()->subMonths($months)->startOfDay();

        $movedDiary = $this->moveRows(
            sourceTable: 'tagebuch',
            archiveTable: 'a_tagebuch',
            dateColumn: 'bis',
            cutoff: $cutoff,
            legacyUserId: $legacyUserId
        );

        $movedOnCall = $this->moveRows(
            sourceTable: 'bereit',
            archiveTable: 'a_bereit',
            dateColumn: 'bis',
            cutoff: $cutoff,
            legacyUserId: $legacyUserId
        );

        $movedNotdienst = $this->moveRows(
            sourceTable: 'notdnst',
            archiveTable: 'a_notdnst',
            dateColumn: 'bis',
            cutoff: $cutoff,
            legacyUserId: $legacyUserId
        );

        return [
            'months' => $months,
            'cutoff' => $cutoff->toDateString(),
            'diary' => $movedDiary,
            'oncall' => $movedOnCall,
            'notdienst' => $movedNotdienst,
            'total' => $movedDiary + $movedOnCall + $movedNotdienst,
        ];
    }

    private function moveRows(string $sourceTable, string $archiveTable, string $dateColumn, Carbon $cutoff, ?int $legacyUserId): int {
        $connection = DB::connection('legacy');

        $query = $connection
            ->table($sourceTable)
            ->where($dateColumn, '<', $cutoff->toDateString())
            ->orderBy('id');

        if ($legacyUserId !== null) {
            $query->where('user', $legacyUserId);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $moved = 0;

        foreach ($rows as $row) {
            $payload = (array) $row;

            $connection->table($archiveTable)->updateOrInsert(
                ['id' => $row->id],
                $payload
            );

            $connection->table($sourceTable)->where('id', $row->id)->delete();
            $moved++;
        }

        return $moved;
    }
}
