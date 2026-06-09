<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanAttachments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use App\Services\Whistleblowing\WhistleblowingAttachmentScanService;
use Illuminate\Console\Command;

/**
 * Prueft ausstehende Hinweisgeber-Anhaenge (Quarantaene → clean/rejected).
 * Ohne konfigurierten Scanner bleiben die Anhaenge fail-safe in Quarantaene.
 */
class ScanAttachments extends Command {
    protected $signature = 'whistleblowing:scan';

    protected $description = 'Prueft ausstehende Hinweisgeber-Anhaenge (Quarantaene-Freigabe).';

    public function handle(WhistleblowingAttachmentScanService $scanner): int {
        $stats = $scanner->scanPending();

        $this->info(sprintf(
            'Anhaenge geprueft: %d (clean: %d, abgelehnt: %d, in Quarantaene belassen: %d).',
            $stats['processed'], $stats['clean'], $stats['rejected'], $stats['skipped'],
        ));

        if ($stats['skipped'] > 0 && (string) config('whistleblowing.scanner', 'none') === 'none') {
            $this->warn('Kein Scanner konfiguriert (WHISTLEBLOWING_SCANNER) – Anhaenge bleiben in Quarantaene.');
        }

        return self::SUCCESS;
    }
}
