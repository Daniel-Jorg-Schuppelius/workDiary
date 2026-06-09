<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClamAvScanDriver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing\Scanning;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * ClamAV-Treiber (clamdscan/clamscan). Setzt voraus, dass das Binary verfuegbar
 * ist (Ops-Entscheidung) und idealerweise in einer gesandboxten Umgebung laeuft.
 *
 * Exit-Codes: 0 = clean, 1 = Schadcode gefunden → rejected, sonst Fehler →
 * null (fail-safe, bleibt in Quarantaene).
 */
class ClamAvScanDriver implements ScanDriver {
    public function scan(string $absolutePath, ?string $mime): ?AttachmentScanStatus {
        $binary = (string) config('whistleblowing.clamav_binary', 'clamdscan');
        $process = new Process([$binary, '--no-summary', '--fdpass', $absolutePath]);
        $process->setTimeout(60);

        try {
            $process->run();
        } catch (Throwable $e) {
            Log::warning('Whistleblowing ClamAV-Scan fehlgeschlagen', ['error' => $e->getMessage()]);

            return null; // fail-safe
        }

        return match ($process->getExitCode()) {
            0 => AttachmentScanStatus::Clean,
            1 => AttachmentScanStatus::Rejected,
            default => null,
        };
    }
}
