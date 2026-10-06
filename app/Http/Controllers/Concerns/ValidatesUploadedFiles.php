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

use App\Services\Attachments\FileAttacher;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Validation\ValidationException;

/**
 * Anhänge eines Formulars (`files[]`): Anzahl, Größe und Positivliste des
 * {@see FileAttacher} — in Helpdesk, Kundenportal-Ticket und Rückfrage
 * dreimal wortgleich (Konsolidierungs-Audit 2026-10, k3-7).
 */
trait ValidatesUploadedFiles {
    /** @return list<UploadedFile> */
    private function validatedUploads(Request $request, int $maxFiles = 5): array {
        $request->validate([
            'files' => ['nullable', 'array', 'max:' . $maxFiles],
            'files.*' => ['file', 'max:' . FileAttacher::maxKb()],
        ]);

        $files = array_values(array_filter((array) $request->file('files', []), static fn ($file): bool => $file instanceof UploadedFile));
        foreach ($files as $file) {
            if (! FileAttacher::accepts($file)) {
                throw ValidationException::withMessages(['files' => (string) __('Dateityp nicht erlaubt.')]);
            }
        }

        return $files;
    }
}
