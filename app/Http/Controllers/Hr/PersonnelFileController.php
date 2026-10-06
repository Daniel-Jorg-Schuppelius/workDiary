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

use App\Enums\Hr\PersonnelFileSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Document\Document;
use App\Models\Hr\PersonnelFileSubmission;
use App\Models\Platform\User;
use App\Services\Document\DocumentService;
use App\Services\Hr\PersonnelFileService;
use App\Support\SortableQuery;
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

    public function index(Request $request, User $member): View {
        Gate::authorize('viewPersonnelFile', [Document::class, $member]);

        return $this->render($request, $member, selfView: false);
    }

    /** Eigenauskunft: die betroffene Person sieht ihre Akte lesend. */
    public function mine(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        Gate::authorize('viewPersonnelFile', [Document::class, $user]);

        return $this->render($request, $user, selfView: true);
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

    /** Lesebestätigung der betroffenen Person (MVP-987). */
    public function acknowledge(Document $document): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $this->service->acknowledge($document, $user);

        return redirect()->toList('account.personnel-file')->with('success', __('hr.personnel_file.flash.acknowledged'))->withFragment('document-' . $document->id);
    }

    private function render(Request $request, User $member, bool $selfView): View {
        $query = Document::query()
            ->personnelFilesOf($member)
            ->with(['currentVersion', 'creator:id,name']);
        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'title' => 'title',
            'category' => 'hr_category',
            'valid_until' => 'valid_until',
            'retention_until' => 'retention_until',
            'updated_at' => 'updated_at',
        ], 'title', 'asc');
        $documents = $query->orderBy('title')->orderBy('id')->paginate(30)->withQueryString();
        $canCreate = ! $selfView && Gate::allows('createPersonnelFile', [Document::class, $member]);
        $submissions = PersonnelFileSubmission::query()->where('user_id', $member->id)
            ->when(! $selfView, fn ($query) => $query->where('status', PersonnelFileSubmissionStatus::Submitted->value))
            ->when($selfView, fn ($query) => $query->where('status', '!=', PersonnelFileSubmissionStatus::Accepted->value))
            ->orderByDesc('created_at')
            ->get();

        return view('hr.personnel-file.index', [
            'member' => $member,
            'documents' => $documents,
            'acknowledgements' => $this->service->acknowledgementsFor($documents),
            // Über die eigene Einreichung entscheidet nie die eigene Person.
            'submissions' => $selfView || ($canCreate && (int) $member->id !== (int) Auth::id()) ? $submissions : collect(),
            'selfView' => $selfView,
            'canCreate' => $canCreate,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }
}
