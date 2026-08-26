<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\{Document, User};
use App\Services\Document\DocumentService;
use App\Services\Hr\PersonnelFileService;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Digitale Personalakte (Feature 141, MVP-708): Akte je Mitglied für den
 * hrFile-Zugriffskreis, Eigenauskunft (read-only) unter „Mein Konto".
 * Download/Versionen/Löschen laufen über die Dokument-Routen — die
 * DocumentPolicy verzweigt für Personalakten auf den hrFile-Kreis.
 */
class PersonnelFileController extends Controller {
    public function __construct(
        private readonly PersonnelFileService $service,
        private readonly DocumentService $documents,
    ) {}

    public function index(User $member): View {
        Gate::authorize('viewPersonnelFile', [Document::class, $member]);

        return $this->render($member, selfView: false);
    }

    /** Eigenauskunft: die betroffene Person sieht ihre Akte lesend. */
    public function mine(): View {
        /** @var User $user */
        $user = Auth::user();
        Gate::authorize('viewPersonnelFile', [Document::class, $user]);

        return $this->render($user, selfView: true);
    }

    public function create(User $member): View {
        Gate::authorize('createPersonnelFile', [Document::class, $member]);

        return view('hr.personnel-file._form_dialog', ['member' => $member, 'document' => null]);
    }

    public function store(Request $request, User $member): RedirectResponse {
        Gate::authorize('createPersonnelFile', [Document::class, $member]);

        $data = $request->validate(PersonnelFileService::rules(includeFile: true));

        /** @var User $actor */
        $actor = Auth::user();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->documents->assertAllowedFile($file);

        $document = $this->service->create($member, $actor, $data, $file);

        return redirect()
            ->back()
            ->with('success', __('hr.personnel_file.flash.created'))
            ->withFragment('document-' . $document->id);
    }

    public function edit(Document $document): View {
        abort_unless($document->isPersonnelFile(), 404);
        Gate::authorize('update', $document);

        return view('hr.personnel-file._form_dialog', [
            'member' => $document->documentable,
            'document' => $document,
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse {
        abort_unless($document->isPersonnelFile(), 404);
        Gate::authorize('update', $document);

        $data = $request->validate(PersonnelFileService::rules(includeFile: false));

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->update($document, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('hr.personnel_file.flash.updated'))
            ->withFragment('document-' . $document->id);
    }

    private function render(User $member, bool $selfView): View {
        $documents = Document::query()
            ->personnelFilesOf($member)
            ->with(['currentVersion', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        return view('hr.personnel-file.index', [
            'member' => $member,
            'documents' => $documents,
            'selfView' => $selfView,
            'canCreate' => ! $selfView && Gate::allows('createPersonnelFile', [Document::class, $member]),
        ]);
    }
}
