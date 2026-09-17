<?php

/*
 * Filename     : ApplicationUploadScanService.php
 * Description  : Quarantaene-Freigabe fuer Unterlagen aus dem oeffentlichen
 *                Karrierebereich.
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Applications\JobApplicationUpload;
use App\Services\Whistleblowing\Scanning\ScanDriver;
use Illuminate\Support\Facades\Storage;

/**
 * Herkunft: Vollscan 2026-09-15, Befund `P6-46`. Unterlagen aus dem
 * oeffentlichen Karrierebereich landeten mit `scan_status = pending` in
 * Quarantaene — und blieben dort: kein Job, kein Befehl, kein Treiberaufruf
 * setzte jemals `clean`. Damit waren eingereichte Bewerbungen fuer die
 * Personalstelle unerreichbar.
 *
 * Der Scan nutzt bewusst denselben {@see ScanDriver} wie das Hinweisgeber-
 * Modul: im Betrieb laeuft ein Virenscanner, nicht zwei. Liefert der Treiber
 * kein Urteil (kein Scanner konfiguriert, Zeitueberschreitung), bleibt die
 * Datei in Quarantaene — fail-safe statt Freigabe im Zweifel.
 */
class ApplicationUploadScanService {
    public function __construct(private readonly ScanDriver $driver) {}

    /**
     * Prueft alle ausstehenden Unterlagen.
     *
     * @return array{processed: int, clean: int, rejected: int, skipped: int}
     */
    public function scanPending(): array {
        $stats = ['processed' => 0, 'clean' => 0, 'rejected' => 0, 'skipped' => 0];

        JobApplicationUpload::query()
            ->withoutGlobalScopes()
            ->where('scan_status', JobApplicationUpload::SCAN_PENDING)
            ->orderBy('id')
            ->chunkById(100, function ($uploads) use (&$stats): void {
                foreach ($uploads as $upload) {
                    $stats['processed']++;
                    $disk = Storage::disk((string) $upload->storage_disk);
                    if (! $disk->exists((string) $upload->storage_key)) {
                        // Datei weg: nicht freigeben, sondern ablehnen — sonst
                        // haengt der Datensatz dauerhaft im Zwischenzustand.
                        $this->markRejected($upload);
                        $stats['rejected']++;

                        continue;
                    }

                    $result = $this->driver->scan(
                        $disk->path((string) $upload->storage_key),
                        (string) $upload->mime ?: null,
                    );

                    if ($result === AttachmentScanStatus::Clean) {
                        $this->markClean($upload);
                        $stats['clean']++;
                    } elseif ($result === AttachmentScanStatus::Rejected) {
                        $this->markRejected($upload);
                        $stats['rejected']++;
                    } else {
                        $stats['skipped']++;
                    }
                }
            });

        return $stats;
    }

    public function markClean(JobApplicationUpload $upload): void {
        $upload->forceFill(['scan_status' => JobApplicationUpload::SCAN_CLEAN])->save();
    }

    public function markRejected(JobApplicationUpload $upload): void {
        $upload->forceFill(['scan_status' => JobApplicationUpload::SCAN_REJECTED])->save();
    }
}
