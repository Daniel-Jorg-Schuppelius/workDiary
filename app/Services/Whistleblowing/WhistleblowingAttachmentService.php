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
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
    public function __construct(
        private readonly WhistleblowingEventService $events,
        private readonly WhistleblowingCryptoService $crypto,
    ) {}

    /**
     * Inhalt eines Anhangs im Klartext - die einzige Stelle, die den Unterschied
     * zwischen verschluesselten und alten Klartext-Dateien kennt.
     */
    public function contents(Attachment $attachment): string {
        $disk = Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'));
        $raw = (string) $disk->get($attachment->storage_key);

        if (! $attachment->encrypted) {
            return $raw; // Bestand von vor dem Sicherheitsaudit 2026-09-13.
        }

        $dek = $attachment->caseDek();
        if (! is_string($dek) || $dek === '') {
            throw new RuntimeException('Fall-Schluessel nicht verfuegbar - Anhang nicht lesbar.');
        }

        return $this->crypto->decryptWithDek($raw, $dek);
    }

    /**
     * Ersetzt den Inhalt eines Anhangs (Metadaten-Bereinigung) und schreibt ihn
     * wieder verschluesselt zurueck. Der Abdruck wandert mit, sonst beschriebe
     * er eine Datei, die es nicht mehr gibt.
     */
    public function replaceContents(Attachment $attachment, string $plaintext): void {
        $dek = $attachment->caseDek();
        if (! is_string($dek) || $dek === '') {
            throw new RuntimeException('Fall-Schluessel nicht verfuegbar - Anhang bleibt unveraendert.');
        }

        Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'))
            ->put($attachment->storage_key, $this->crypto->encryptWithDek($plaintext, $dek));

        $attachment->forceFill([
            'encrypted' => true,
            'size' => strlen($plaintext),
            'sha256' => CryptoHelper::hash($plaintext, HashAlgorithm::SHA256),
        ])->save();
    }

    /**
     * Fuehrt $fn mit einem Klartext-Pfad aus - fuer Verbraucher, die eine echte
     * Datei brauchen (Virenscanner, ZIP-Export). Die Temporaerdatei verschwindet
     * in jedem Fall wieder.
     *
     * @template TReturn
     *
     * @param  callable(string): TReturn  $fn
     * @return TReturn
     */
    public function withPlaintextFile(Attachment $attachment, callable $fn): mixed {
        $disk = Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'));

        if (! $attachment->encrypted) {
            return $fn($disk->path($attachment->storage_key));
        }

        // Klartext nur in einer 0600-Temporaerdatei, die das Toolkit immer wieder loescht.
        return File::withTemp($this->contents($attachment), $fn, 'wbatt');
    }

    public function storeReporterUpload(WhistleblowingCase $case, UploadedFile $file): Attachment {
        $this->guard($case, $file);

        $disk = (string) config('whistleblowing.disk', 'whistleblowing');
        $key = 'cases/' . $case->getKey() . '/' . Str::random(40);

        // Mit dem Fall-Schluessel verschluesseln (Sicherheitsaudit 2026-09-13):
        // ohne das wirkt das Crypto-Shredding beim Loeschen eines Falls nur auf
        // Text, waehrend die Beweismittel in jedem Backup im Klartext liegen
        // bleiben - samt EXIF/Autor, also genau der Enttarnungsweg.
        $dek = $case->caseDek();
        if (! is_string($dek) || $dek === '') {
            throw new RuntimeException('Fall-Schluessel nicht verfuegbar - Anhang wird nicht abgelegt.');
        }
        try {
            $plaintext = File::read((string) $file->getRealPath());
        } catch (Throwable) {
            throw new RuntimeException('Konnte die hochgeladene Datei nicht lesen.');
        }
        Storage::disk($disk)->put($key, $this->crypto->encryptWithDek($plaintext, $dek));

        $attachment = new Attachment;
        $attachment->organization_id = $case->getAttribute('organization_id');
        $attachment->case_id = $case->getKey();
        $attachment->setRelation('case', $case); // DEK fuer den Cast verfuegbar machen
        $attachment->uploaded_by_type = 'reporter';
        $attachment->storage_key = $key;
        $attachment->encrypted = true;
        $attachment->original_name_ciphertext = $file->getClientOriginalName();
        $attachment->mime_detected = $file->getMimeType(); // serverseitig aus Inhalt
        $attachment->size = (int) $file->getSize();
        try {
            $attachment->sha256 = File::hash($file->getRealPath(), HashAlgorithm::SHA256);
        } catch (Throwable) {
            // Null-Semantik wie zuvor (hash_file(...) ?: null): Lesefehler ⇒ kein Hash.
            $attachment->sha256 = null;
        }
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
