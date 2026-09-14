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
use Illuminate\Support\Facades\{Log, Storage};

/**
 * Steuert den Freigabe-/Quarantaene-Status von Anhaengen (Abschnitt 11 / 25).
 * Der eigentliche Malware-Scan laeuft im pluggbaren {@see ScanDriver} (idealer-
 * weise gesandboxt); dieser Service kapselt nur die Status-Uebergaenge.
 */
class WhistleblowingAttachmentScanService {
    public function __construct(
        private readonly WhistleblowingEventService $events,
        private readonly ScanDriver $driver,
        private readonly WhistleblowingAttachmentService $attachments,
        private readonly WhistleblowingMetadataScrubber $scrubber,
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
            ->chunkById(100, function ($attachments) use (&$stats): void {
                foreach ($attachments as $attachment) {
                    $stats['processed']++;
                    // Der Scanner braucht eine echte Datei; verschluesselte Anhaenge
                    // (Sicherheitsaudit 2026-09-13) werden dafuer kurz ausgepackt.
                    $result = $this->attachments->withPlaintextFile(
                        $attachment,
                        fn (string $path) => $this->driver->scan($path, $attachment->mime_detected),
                    );

                    if ($result === null) {
                        $stats['skipped']++; // kein Urteil → bleibt in Quarantaene
                        continue;
                    }
                    if ($result === AttachmentScanStatus::Clean) {
                        // Erst bereinigen, dann freigeben: ein Beweisfoto traegt
                        // Aufnahmezeit, Geraet und oft GPS — die Meldung ist anonym,
                        // das Foto nicht (Sicherheitsaudit 2026-09-13).
                        $this->scrubMetadata($attachment);
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

    /**
     * Metadaten entfernen, soweit der Bereiniger den Typ beherrscht. Bilder
     * werden neu kodiert; PDF und Office bleiben als "nicht bereinigt"
     * gekennzeichnet, damit die Luecke sichtbar bleibt.
     */
    private function scrubMetadata(Attachment $attachment): void {
        if (! $this->scrubber->supports($attachment->mime_detected)) {
            return;
        }

        try {
            $scrubbed = $this->scrubber->scrub($this->attachments->contents($attachment), $attachment->mime_detected);
            if ($scrubbed === null) {
                return;
            }
            $this->attachments->replaceContents($attachment, $scrubbed);
            $attachment->forceFill(['metadata_scrubbed' => true])->save();
        } catch (\Throwable $e) {
            // Eine fehlgeschlagene Bereinigung darf den Anhang nicht verlieren:
            // er bleibt unveraendert und als "nicht bereinigt" gekennzeichnet.
            Log::warning('whistleblowing.metadata_scrub_failed', [
                'attachment_id' => (int) $attachment->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
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
