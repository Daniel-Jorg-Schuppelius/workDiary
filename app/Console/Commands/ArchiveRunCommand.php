<?php

namespace App\Console\Commands;

use App\Services\Archive\ArchiveService;
use Illuminate\Console\Command;

class ArchiveRunCommand extends Command {
    protected $signature = 'archive:run {--days= : Schwellwert in Tagen (überschreibt config)}';

    protected $description = 'Archiviert erledigte Tagebucheinträge sowie abgelaufene Bereitschaft/Notdienste älter als der Schwellwert.';

    public function handle(ArchiveService $service): int {
        $days = $this->option('days');
        $thresholdDays = is_numeric($days) ? (int) $days : null;

        $result = $service->run($thresholdDays);

        $this->table(
            ['Bereich', 'Archiviert'],
            [
                ['Tagebuch', (string) $result['diary']],
                ['Bereitschaft', (string) $result['shifts']],
                ['Notdienst', (string) $result['assignments']],
                ['Gesamt', (string) $result['total']],
            ]
        );

        $this->info('Archivierung abgeschlossen (Stichtag: ' . $result['cutoff'] . ').');

        return self::SUCCESS;
    }
}
