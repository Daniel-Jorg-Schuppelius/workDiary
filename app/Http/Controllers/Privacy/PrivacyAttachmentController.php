<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyAttachmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\{DataSubjectRequest, Incident, PrivacyAttachment, TechnicalMeasure};
use App\Services\Privacy\{DataProtectionCryptoService, SubjectDataExporter};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Symfony\Component\HttpFoundation\Response;

/**
 * Anhaenge an Datenschutz-Fallakten. Upload setzt das `update`-Recht der Akte
 * voraus, Download das `view`-Recht; Dateien liegen auf der privaten Disk.
 */
class PrivacyAttachmentController extends Controller {
    public function storeForRequest(Request $request, DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('update', $dsr);
        $this->upload($request, $dsr);

        return back()->with('status', __('Anhang hinzugefügt.'));
    }

    public function storeForIncident(Request $request, Incident $incident): RedirectResponse {
        Gate::authorize('update', $incident);
        $this->upload($request, $incident);

        return back()->with('status', __('Anhang hinzugefügt.'));
    }

    /**
     * TOM-Nachweisanhang (Nachtrag 043b): Zertifikat/Auditbericht mit
     * optionalem Gültig-bis — abgelaufene Nachweise meldet der
     * Compliance-Check (tom_proof_current).
     */
    public function storeForMeasure(Request $request, TechnicalMeasure $measure): RedirectResponse {
        Gate::authorize('update', $measure);
        $data = $request->validate(['valid_until' => ['nullable', 'date']]);
        $attachment = $this->upload($request, $measure);
        if ($attachment !== null && ($data['valid_until'] ?? null) !== null) {
            $attachment->forceFill(['valid_until' => $data['valid_until']])->save();
        }

        return back()->with('status', __('Nachweis hinzugefügt.'));
    }

    public function download(PrivacyAttachment $attachment): Response {
        $parent = $attachment->attachable;
        abort_unless($parent instanceof Model, 404);
        Gate::authorize('view', $parent);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        // Generierte Auskunftspakete (Feature 129) liegen mit dem Fall-DEK
        // verschlüsselt — hier entschlüsseln; nach Crypto-Shredding: 410.
        if (str_starts_with($attachment->path, SubjectDataExporter::STORAGE_PREFIX)) {
            abort_unless($parent instanceof DataSubjectRequest, 404);
            $dek = $parent->recordDek();
            abort_if($dek === null, 410, __('Der Fall wurde kryptografisch geschreddert.'));

            $cipher = Storage::disk('local')->get($attachment->path);
            abort_unless(is_string($cipher), 404);
            $plain = app(DataProtectionCryptoService::class)->decryptWithDek($cipher, $dek);

            return response($plain, 200, [
                'Content-Type' => (string) ($attachment->mime ?: 'application/octet-stream'),
                'Content-Disposition' => 'attachment; filename="' . $attachment->filename . '"',
            ]);
        }

        return response()->download(Storage::disk('local')->path($attachment->path), $attachment->filename);
    }

    public function destroy(PrivacyAttachment $attachment): RedirectResponse {
        $parent = $attachment->attachable;
        abort_unless($parent instanceof Model, 404);
        Gate::authorize('update', $parent);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Anhang entfernt.'));
    }

    private function upload(Request $request, Model $attachable): ?PrivacyAttachment {
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,txt']]);
        $file = $request->file('file');
        $stored = $file->store('privacy/attachments', 'local');
        if ($stored === false) {
            return null;
        }

        return PrivacyAttachment::create([
            'organization_id' => $attachable->getAttribute('organization_id'),
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'filename' => \App\Support\Filename::sanitize($file->getClientOriginalName()),
            'path' => $stored,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'uploaded_by' => $request->user()?->id,
        ]);
    }
}
