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
use App\Models\Privacy\{DataSubjectRequest, Incident, PrivacyAttachment};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function download(PrivacyAttachment $attachment): BinaryFileResponse {
        $parent = $attachment->attachable;
        abort_unless($parent instanceof Model, 404);
        Gate::authorize('view', $parent);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

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

    private function upload(Request $request, Model $attachable): void {
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,txt']]);
        $file = $request->file('file');
        $stored = $file->store('privacy/attachments', 'local');
        if ($stored === false) {
            return;
        }

        PrivacyAttachment::create([
            'organization_id' => $attachable->getAttribute('organization_id'),
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'filename' => $file->getClientOriginalName(),
            'path' => $stored,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'uploaded_by' => $request->user()?->id,
        ]);
    }
}
