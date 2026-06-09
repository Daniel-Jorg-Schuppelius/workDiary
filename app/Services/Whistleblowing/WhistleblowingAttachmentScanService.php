<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingAttachmentScanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Whistleblowing\Attachment;
use App\Services\Whistleblowing\Scanning\ScanDriver;
use Illuminate\Support\Facades\Storage;

/**
 * Steuert den Freigabe-/Quarantaene-Status von Anhaengen (Abschnitt 11 / 25).
 * Der eigentliche Malware-Scan laeuft im pluggbaren {@see ScanDriver} (idealer-
 * weise gesandboxt); dieser Service kapselt nur die Status-Uebergaenge.
 */
class WhistleblowingAttachmentScanService {
    public function __construct(
        private readonly WhistleblowingEventService $events,
        private readonly ScanDriver $driver,
    ) {}

    /**
     * Verarbeitet alle ausstehenden Anhaenge mit dem konfigurierten Scanner.
     *
     * @return array{processed:int, clean:int, rejected:int, skipped:int}
     */
    public function scanPending(): array {
        $disk = Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'));
        $stats = ['processed' => 0, 'clean' => 0, 'rejected' => 0, 'skipped' => 0];

        Attachment::withoutGlobalScopes()
            ->where('scan_status', AttachmentScanStatus::Pending->value)
            ->chunkById(100, function ($attachments) use ($disk, &$stats): void {
                foreach ($attachments as $attachment) {
                    $stats['processed']++;
                    $result = $this->driver->scan($disk->path($attachment->storage_key), $attachment->mime_detected);

                    if ($result === null) {
                        $stats['skipped']++; // kein Urteil → bleibt in Quarantaene
                        continue;
                    }
                    if ($result === AttachmentScanStatus::Clean) {
                        $this->markClean($attachment);
                        $stats['clean']++;
                    } else {
                        $this->markRejected($attachment);
                        $stats['rejected']++;
                    }
                }
            });

        return $stats;
    }

    public function markClean(Attachment $attachment): void {
        $attachment->forceFill(['scan_status' => AttachmentScanStatus::Clean->value])->save();
    }

    public function markRejected(Attachment $attachment): void {
        $attachment->forceFill(['scan_status' => AttachmentScanStatus::Rejected->value])->save();

        $case = $attachment->case;
        if ($case !== null) {
            $this->events->record($case, WhistleblowingEventService::ATTACHMENT_REJECTED, null, [
                'attachment_id' => (int) $attachment->getKey(),
            ]);
        }
    }
}
