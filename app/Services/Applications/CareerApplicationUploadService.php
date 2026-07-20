<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CareerApplicationUploadService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Models\Applications\{JobApplication, JobApplicationUpload};
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gehärtete Ablage öffentlich hochgeladener Bewerbungsunterlagen (MVP-437).
 *
 * Positivliste, **serverseitige** MIME-Prüfung (nicht der Client-Header),
 * Mengen-/Größenlimits, privater Disk mit Zufallsschlüssel, sha256 und
 * Quarantäne (`scan_status=pending`) — freigegeben erst nach erfolgreichem
 * Malware-Scan. Spiegelt das Muster von
 * {@see \App\Services\Whistleblowing\WhistleblowingAttachmentService}, ohne
 * dessen Fall-/DMS-Bindung.
 */
class CareerApplicationUploadService {
    public const DISK = 'local';
    public const MAX_FILES = 5;
    public const MAX_BYTES = 5 * 1024 * 1024;      // je Datei
    public const MAX_TOTAL_BYTES = 15 * 1024 * 1024; // gesamt

    /** @var array<string, list<string>> erlaubte MIME → erlaubte Endungen */
    public const ALLOWED = [
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    /**
     * @param  array<int, mixed>  $files  Roh-Upload-Eingabe (unsicher, wird gefiltert)
     * @return int  Anzahl gespeicherter (quarantänisierter) Dateien
     *
     * @throws ValidationException
     */
    public function store(JobApplication $application, array $files): int {
        $files = array_values(array_filter($files, static fn ($f) => $f instanceof UploadedFile));
        if ($files === []) {
            return 0;
        }
        if (count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['documents' => (string) __('careers.upload.tooMany', ['max' => self::MAX_FILES])]);
        }

        $total = 0;
        foreach ($files as $file) {
            $total += (int) $file->getSize();
            $this->assertAcceptable($file);
        }
        if ($total > self::MAX_TOTAL_BYTES) {
            throw ValidationException::withMessages(['documents' => (string) __('careers.upload.tooLarge')]);
        }

        $stored = 0;
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $key = 'careers/' . $application->organization_id . '/' . $application->id . '/' . Str::random(40) . ($ext !== '' ? '.' . $ext : '');
            $path = $file->storeAs('', $key, ['disk' => self::DISK]);
            if ($path === false) {
                continue;
            }
            $absolute = Storage::disk(self::DISK)->path($key);

            JobApplicationUpload::query()->create([
                'organization_id' => $application->organization_id,
                'job_application_id' => $application->id,
                'storage_disk' => self::DISK,
                'storage_key' => $key,
                'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                'mime' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'sha256' => ToolkitFile::hash($absolute),
                'scan_status' => JobApplicationUpload::SCAN_PENDING,
            ]);
            $stored++;
        }

        return $stored;
    }

    /**
     * Serverseitige Positivlisten-Prüfung: echter Inhalts-MIME **und** Endung
     * müssen zueinander in der Allowlist stehen.
     *
     * @throws ValidationException
     */
    private function assertAcceptable(UploadedFile $file): void {
        $mime = (string) $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        $allowedExts = self::ALLOWED[$mime] ?? null;
        if ($allowedExts === null || ! in_array($ext, $allowedExts, true)) {
            throw ValidationException::withMessages([
                'documents' => (string) __('careers.upload.type', ['name' => $file->getClientOriginalName()]),
            ]);
        }
        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'documents' => (string) __('careers.upload.tooLargeFile', ['name' => $file->getClientOriginalName(), 'mb' => 5]),
            ]);
        }
    }
}
