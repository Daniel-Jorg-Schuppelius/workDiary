<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyArchiveService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyArchiveService
{
    /** @return array<string, mixed> */
    public function archiveOlderThanMonths(int $months, ?int $legacyUserId = null): array
    {
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

    private function moveRows(string $sourceTable, string $archiveTable, string $dateColumn, Carbon $cutoff, ?int $legacyUserId): int
    {
        $connection = DB::connection('legacy');

        $query = $connection
            ->table($sourceTable)
            ->where($dateColumn, '<', $cutoff->toDateString())
            ->orderBy('id');

        if ($legacyUserId !== null) {
            $query->where('user', $legacyUserId);
        }

        $moved = 0;

        $query->chunkById(250, function ($rows) use ($archiveTable, $connection, $sourceTable, &$moved): void {
            if ($rows->isEmpty()) {
                return;
            }

            $connection->transaction(function () use ($archiveTable, $connection, $rows, $sourceTable): void {
                $ids = [];

                foreach ($rows as $row) {
                    $payload = (array) $row;
                    $ids[] = $row->id;

                    $connection->table($archiveTable)->updateOrInsert(
                        ['id' => $row->id],
                        $payload
                    );
                }

                $connection->table($sourceTable)->whereIn('id', $ids)->delete();
            });

            $moved += $rows->count();
        }, 'id');

        return $moved;
    }
}
