<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyArchiveCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Console\Commands;

use App\Legacy\Services\LegacyArchiveService;
use Illuminate\Console\Command;

class LegacyArchiveCommand extends Command
{
    protected $signature = 'legacy:archive {months : 3, 6, 9 oder 12 Monate} {--user= : Optional Legacy User-ID}';

    protected $description = 'Verschiebt alte Legacy-Daten in die Archivtabellen a_tagebuch, a_bereit, a_notdnst';

    public function handle(LegacyArchiveService $service): int
    {
        $months = (int) $this->argument('months');

        if (! in_array($months, [3, 6, 9, 12], true)) {
            $this->error('Monate müssen 3, 6, 9 oder 12 sein.');

            return self::FAILURE;
        }

        $user = $this->option('user');
        $legacyUserId = is_numeric($user) ? (int) $user : null;

        $result = $service->archiveOlderThanMonths($months, $legacyUserId);

        $this->table(
            ['Bereich', 'Verschoben'],
            [
                ['Aufträge', (string) $result['diary']],
                ['Bereitschaft', (string) $result['oncall']],
                ['Notdienst', (string) $result['notdienst']],
                ['Gesamt', (string) $result['total']],
            ]
        );

        $this->info('Archivierung bis Stichtag '.$result['cutoff'].' abgeschlossen.');

        return self::SUCCESS;
    }
}
