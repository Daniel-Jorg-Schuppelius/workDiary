<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingAttachmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\Whistleblowing\{Attachment, WhistleblowingCase};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Nimmt Meldeanhaenge entgegen (Abschnitt 11 / 25): privater Disk, zufaelliger
 * storage_key, MIME aus dem Dateiinhalt (nicht aus der Browserangabe), Positiv-
 * liste, harte Groessen-/Mengenlimits, sha256. Der Anhang startet in QUARANTAENE
 * (`scan_status = pending`) und wird Bearbeitern erst nach `clean` ausgeliefert.
 *
 * Hinweis: Malware-Scan (ClamAV) und Metadaten-Scrubbing laufen bewusst in einem
 * gesandboxten Worker (Abschnitt 25) und sind hier noch NICHT aktiv – die
 * Quarantaene haelt den Anhang bis dahin zurueck.
 */
class WhistleblowingAttachmentService {
    public function __construct(private readonly WhistleblowingEventService $events) {}

    public function storeReporterUpload(WhistleblowingCase $case, UploadedFile $file): Attachment {
        $this->guard($case, $file);

        $disk = (string) config('whistleblowing.disk', 'whistleblowing');
        $key = 'cases/' . $case->getKey() . '/' . Str::random(40);

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Konnte die hochgeladene Datei nicht lesen.');
        }
        Storage::disk($disk)->put($key, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $attachment = new Attachment;
        $attachment->organization_id = $case->getAttribute('organization_id');
        $attachment->case_id = $case->getKey();
        $attachment->setRelation('case', $case); // DEK fuer den Cast verfuegbar machen
        $attachment->uploaded_by_type = 'reporter';
        $attachment->storage_key = $key;
        $attachment->original_name_ciphertext = $file->getClientOriginalName();
        $attachment->mime_detected = $file->getMimeType(); // serverseitig aus Inhalt
        $attachment->size = (int) $file->getSize();
        $attachment->sha256 = hash_file('sha256', $file->getRealPath()) ?: null;
        $attachment->scan_status = AttachmentScanStatus::Pending;
        $attachment->metadata_scrubbed = false;
        $attachment->save();

        $this->events->record($case, WhistleblowingEventService::ATTACHMENT_UPLOADED, null, [
            'mime' => $attachment->mime_detected,
            'size' => $attachment->size,
        ]);

        return $attachment;
    }

    private function guard(WhistleblowingCase $case, UploadedFile $file): void {
        $cfg = (array) config('whistleblowing.uploads');

        if (! $file->isValid()) {
            throw new RuntimeException('Ungueltiger Upload.');
        }

        $maxBytes = (int) ($cfg['max_bytes'] ?? 0);
        if ($maxBytes > 0 && (int) $file->getSize() > $maxBytes) {
            throw new RuntimeException('Datei ueberschreitet die maximale Groesse.');
        }

        $allowed = (array) ($cfg['allowed_mimes'] ?? []);
        if ($allowed !== [] && ! in_array($file->getMimeType(), $allowed, true)) {
            throw new RuntimeException('Dateityp nicht erlaubt.');
        }

        $count = $case->attachments()->count();
        if ($count >= (int) ($cfg['max_per_case'] ?? PHP_INT_MAX)) {
            throw new RuntimeException('Maximale Anzahl Anhaenge erreicht.');
        }

        $maxTotal = (int) ($cfg['max_total_bytes'] ?? 0);
        if ($maxTotal > 0) {
            $used = (int) $case->attachments()->sum('size');
            if ($used + (int) $file->getSize() > $maxTotal) {
                throw new RuntimeException('Gesamt-Quota fuer Anhaenge ueberschritten.');
            }
        }
    }
}
