<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportArchiveService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Services;

use App\Legacy\Models\LegacySupportOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Verschiebt archivierte Support-Aufträge von `kp_auftrag` nach `kp_archiv`
 * wie die Archivierung des Altportals. Danach sieht der Kunde sie nicht mehr.
 */
class LegacySupportArchiveService {
    public function eligibleCount(CarbonInterface $before): int {
        return $this->eligible($before)->count();
    }

    public function archiveBefore(CarbonInterface $before): int {
        $connection = DB::connection('legacy');
        $moved = 0;

        // Die Tabellen sind MyISAM: die Transaktion schützt nichts; updateOrInsert
        // hält einen abgebrochenen Lauf wiederholbar.
        $this->eligible($before)->chunkById(250, function ($rows) use ($connection, &$moved): void {
            $connection->transaction(function () use ($connection, $rows): void {
                $ids = [];
                foreach ($rows as $row) {
                    $ids[] = $row->id;
                    $connection->table('kp_archiv')->updateOrInsert(['id' => $row->id], (array) $row);
                }

                $connection->table('kp_auftrag')->whereIn('id', $ids)->delete();
            });

            $moved += $rows->count();
        }, 'id');

        return $moved;
    }

    private function eligible(CarbonInterface $before): Builder {
        return DB::connection('legacy')->table('kp_auftrag')
            ->where('status', LegacySupportOrder::STATUS_ARCHIVED)
            ->where('datum', '<', $before->format('Y-m-d H:i:s'));
    }
}
