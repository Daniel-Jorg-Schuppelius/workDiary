<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValidatesUploadedFiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\Attachments\UploadPurpose;
use App\Services\Attachments\FileAttacher;
use CommonToolkit\Helper\FileSystem\File;
use CommonToolkit\ValueObjects\ByteSize;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Validation\ValidationException;

/**
 * Anhänge eines Formulars (`files[]`): Anzahl, Größe je Datei, Gesamtgröße
 * und Positivliste des Upload-Zwecks ({@see FileAttacher}) — in Helpdesk,
 * Kundenportal-Ticket, Rückfrage und Kundeneingang gleich. Fehler nennen die
 * betroffene Datei (`files.<n>`), damit keine Teileinreichung unbemerkt bleibt.
 */
trait ValidatesUploadedFiles {
    /** @return list<UploadedFile> */
    private function validatedUploads(Request $request, int $maxFiles = 5, UploadPurpose $purpose = UploadPurpose::General, string $field = 'files'): array {
        $raw = (array) $request->file($field, []);
        $names = [];
        foreach ($raw as $index => $file) {
            if ($file instanceof UploadedFile) {
                $names[$field . '.' . $index] = '„' . File::sanitizeDisplayName($file->getClientOriginalName()) . '“';
            }
        }

        $request->validate([
            $field => ['nullable', 'array', 'max:' . $maxFiles],
            $field . '.*' => ['file', 'max:' . FileAttacher::effectiveMaxKb($purpose)],
        ], [], $names);

        $files = [];
        $errors = [];
        $total = 0;
        foreach ($raw as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if (! FileAttacher::accepts($file, $purpose)) {
                $errors[$field . '.' . $index] = (string) __('uploads.error.type', ['name' => $names[$field . '.' . $index] ?? '']);
            }
            $total += (int) $file->getSize();
            $files[] = $file;
        }

        $totalKb = FileAttacher::effectiveTotalKb($purpose);
        if ($total > $totalKb * 1024) {
            $errors[$field] = (string) __('uploads.error.total', ['size' => ByteSize::ofBytes($totalKb * 1024)->format(0)]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $files;
    }
}
