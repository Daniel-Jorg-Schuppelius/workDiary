<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\{Document, DocumentVersion, User};
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Portal-Dokumentensicht (Feature 031/012, Welle D — Dokument-Spiegelung ins
 * Kundenportal): NUR fürs Kundenportal freigegebene Dokumente des eigenen
 * Kundenkontos bzw. der eigenen Aufträge/Projekte/Objekte. Harte org-/kunden-
 * Scope-Grenze über {@see Document::scopeVisibleToCustomer()} — interne oder
 * fremde Dokumente sind hier prinzipiell nicht sichtbar oder ladbar.
 */
class DocumentController extends Controller {
    public function index(): View {
        $user = $this->portalUser();

        $documents = Document::query()
            ->visibleToCustomer((int) $user->organization_id, (int) $user->customer_id)
            ->with(['currentVersion', 'documentable'])
            ->latest('customer_released_at')
            ->paginate(25);

        return view('customer.documents.index', ['documents' => $documents]);
    }

    /**
     * Sicherer Portal-Download: nur eine freigegebene Version eines
     * freigegebenen Dokuments des eigenen Kunden. Pfade stammen ausschließlich
     * aus der DB (UUID-Dateinamen) und werden über den Storage-Disk aufgelöst —
     * kein Client-Input im Pfad, Directory-Traversal ausgeschlossen.
     */
    public function download(Document $document, ?DocumentVersion $version = null): BinaryFileResponse {
        $user = $this->portalUser();

        // Leak-Schutz: existiert das Dokument nicht in der freigegebenen
        // Kundensicht, ist es für dieses Portalkonto schlicht nicht vorhanden.
        $visible = Document::query()
            ->visibleToCustomer((int) $user->organization_id, (int) $user->customer_id)
            ->whereKey($document->getKey())
            ->exists();
        abort_unless($visible, 404);

        if ($version === null) {
            /** @var DocumentVersion|null $version */
            $version = $document->currentVersion;
        }
        if ($version === null || (int) $version->document_id !== (int) $document->id) {
            abort(404);
        }

        $disk = Storage::disk($version->disk);
        if (! $disk->exists($version->path)) {
            abort(404);
        }

        return response()->download($disk->path($version->path), $version->original_name);
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
