<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplyTombstones.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use App\Services\Whistleblowing\WhistleblowingDeletionService;
use Illuminate\Console\Command;

/**
 * Nach einem Restore: Löschungen aus dem überlebenden Tombstone-Ledger erneut
 * anwenden (Konzept Hinweisgebersystem §16).
 */
class ApplyTombstones extends Command {
    protected $signature = 'whistleblowing:apply-tombstones
        {--ledger= : Pfad des Ledgers (Standard: whistleblowing.tombstone_ledger)}
        {--dry-run : nur zählen, nichts löschen}';

    protected $description = 'Wendet nach einem Restore die gelöschten Hinweisgeber-Fälle aus dem Tombstone-Ledger erneut an.';

    public function handle(WhistleblowingDeletionService $deletion): int {
        $ledger = $this->option('ledger');
        $result = $deletion->reapplyLedger(is_string($ledger) && $ledger !== '' ? $ledger : null, (bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%s: %d Fälle geschreddert, %d Grabsteine wiederhergestellt, %d Einträge ohne Fall.',
            $this->option('dry-run') ? 'Probelauf' : 'Angewandt',
            $result['shredded'],
            $result['tombstones_restored'],
            $result['unknown'],
        ));

        return self::SUCCESS;
    }
}
