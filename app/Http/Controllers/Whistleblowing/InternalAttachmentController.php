<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InternalAttachmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Whistleblowing;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Whistleblowing\{Attachment, WhistleblowingCase};
use Illuminate\Support\Facades\{Gate, Storage};
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert Meldeanhaenge NUR an berechtigte Bearbeiter und NUR nach erfolgreicher
 * Pruefung aus (scan_status = clean, Abschnitt 11). Pending/rejected/failed werden
 * zurueckgehalten (Quarantaene). Download nur ueber diesen autorisierten Pfad,
 * nie ueber einen direkten Webserverpfad.
 */
class InternalAttachmentController extends Controller {
    public function download(WhistleblowingCase $case, Attachment $attachment): StreamedResponse {
        Gate::authorize('view', $case);
        abort_unless((int) $attachment->case_id === (int) $case->getKey(), 404);

        // Quarantaene-Gate: nur freigegebene Anhaenge.
        abort_unless($attachment->scan_status === AttachmentScanStatus::Clean, 403);

        $disk = Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'));
        abort_unless($disk->exists($attachment->storage_key), 404);

        $filename = $this->safeFilename((string) ($attachment->original_name_ciphertext ?? 'anhang'));

        return $disk->download($attachment->storage_key, $filename, [
            'Content-Type' => $attachment->mime_detected ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    private function safeFilename(string $name): string {
        $name = str_replace(["\r", "\n", '"', '/', '\\'], '', basename($name));
        $name = trim($name);

        return $name === '' ? 'anhang' : $name;
    }
}
