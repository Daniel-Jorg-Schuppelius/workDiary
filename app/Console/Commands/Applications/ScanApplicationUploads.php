<?php

/*
 * Filename     : ScanApplicationUploads.php
 * Description  : Gibt geprüfte Bewerbungsunterlagen aus der Quarantäne frei.
 */

declare(strict_types=1);

namespace App\Console\Commands\Applications;

use App\Services\Applications\ApplicationUploadScanService;
use Illuminate\Console\Command;

/**
 * Gegenstueck zu `whistleblowing:scan` fuer den Karrierebereich
 * (Vollscan 2026-09-15, Befund `P6-46`).
 */
class ScanApplicationUploads extends Command {
    protected $signature = 'recruiting:scan-uploads';

    protected $description = 'Prueft ausstehende Bewerbungsunterlagen (Quarantaene-Freigabe).';

    public function handle(ApplicationUploadScanService $scanner): int {
        $stats = $scanner->scanPending();

        $this->info(sprintf(
            'Unterlagen geprueft: %d (clean: %d, abgelehnt: %d, in Quarantaene belassen: %d).',
            $stats['processed'], $stats['clean'], $stats['rejected'], $stats['skipped'],
        ));

        if ($stats['skipped'] > 0 && (string) config('whistleblowing.scanner', 'none') === 'none') {
            $this->warn('Kein Scanner konfiguriert (WHISTLEBLOWING_SCANNER) – Unterlagen bleiben in Quarantaene.');
        }

        return self::SUCCESS;
    }
}
