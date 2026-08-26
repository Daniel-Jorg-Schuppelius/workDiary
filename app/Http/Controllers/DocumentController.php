<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Asset, Customer, DiaryEntry, Document, DocumentVersion, Project, User};
use App\Services\Attachments\FileAttacher;
use App\Services\Document\DocumentService;
use App\Services\Hr\PersonnelFileService;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller {
    /**
     * Whitelist der erlaubten Bezugs-Typen. Verhindert, dass Aufrufer
     * beliebige Klassen an `documentable_type` setzen können.
     *
     * @var array<string, class-string<Model>>
     */
    private const DOCUMENTABLE_MAP = [
        'customer' => Customer::class,
        'project' => Project::class,
        'diary' => DiaryEntry::class,
        'asset' => Asset::class,
        // Personalakte (Feature 141): eigener hrFile-Zugriffskreis, siehe DocumentPolicy.
        'user' => User::class,
    ];

    // Größenlimit: {@see FileAttacher::maxKb()} (wie AttachmentController, org-konfigurierbar).

    // Erlaubte Endungen/MIME-Typen: {@see DocumentService::ALLOWED_EXTENSIONS} /
    // {@see DocumentService::ALLOWED_MIMES} (MVP-707: geteilt mit dem Dokument-ZIP-Import).

    public function __construct(
        private readonly DocumentService $service,
        private readonly PersonnelFileService $personnelFiles,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Document::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', 'all'),
            'status' => (string) $request->query('status', 'all'),
            'ref' => (string) $request->query('ref', 'all'),
            'expiring' => (string) $request->query('expiring', 'all'),
        ];

        /** @var User $viewer */
        $viewer = Auth::user();
        $query = Document::query()
            // Vertrauliche Dokumente Dritter ausblenden (Vollaudit 2026-07, N10).
            ->visibleTo($viewer)
            ->with(['currentVersion', 'documentable', 'creator'])
            ->latest('updated_at');

        if ($filters['q'] !== '') {
            $query->whereLikeEscaped('title', $filters['q']);
        }
        if (DocumentType::tryFrom($filters['type']) !== null) {
            $query->where('document_type', $filters['type']);
        }
        if ($filters['status'] !== 'all') {
            $this->applyStatusFilter($query, $filters['status']);
        }
        if ($filters['ref'] !== 'all') {
            $this->applyRefFilter($query, $filters['ref']);
        }
        if (in_array($filters['expiring'], ['30', '60', '90'], true)) {
            $query->expiringWithin((int) $filters['expiring']);
        }

        $documents = $query->paginate(25)->withQueryString();

        $hasActiveFilters = $filters['q'] !== ''
            || $filters['type'] !== 'all'
            || $filters['status'] !== 'all'
            || $filters['ref'] !== 'all'
            || $filters['expiring'] !== 'all';

        return view('documents.index', [
            'documents' => $documents,
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'canCreate' => Gate::allows('create', Document::class),
        ]);
    }

    /**
     * Read-only-Detailseite (Rang 28): Trägerseite für Stammdaten, Versionen
     * und das Externe-Beteiligte-Panel.
     */
    public function show(Document $document): View {
        Gate::authorize('view', $document);

        $this->auditConfidentialAccess($document);

        $document->load([
            'versions.uploader:id,name',
            'currentVersion',
            'documentable',
            'creator:id,name',
            'customerReleaser:id,name',
        ]);

        return view('documents.show', [
            'document' => $document,
        ]);
    }

    public function create(Request $request): View {
        // Personalakte (Feature 141): eigener Dialog + hrFile-Kreis statt document.create.
        if ((string) $request->query('documentable_kind', '') === 'user') {
            $member = $this->findMember((string) $request->query('documentable_id', ''));
            Gate::authorize('createPersonnelFile', [Document::class, $member]);

            return view('hr.personnel-file._form_dialog', ['member' => $member, 'document' => null]);
        }

        Gate::authorize('create', Document::class);

        [$documentableKind, $documentableId] = $this->resolveOptionalDocumentableFromRequest($request);

        return view('documents._form_dialog', [
            'document' => null,
            'documentableKind' => $documentableKind,
            'documentableId' => $documentableId,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        // Personalakte (Feature 141): Upload über den hrFile-Kreis, vertraulich erzwungen.
        if ((string) $request->input('documentable_kind', '') === 'user') {
            $member = $this->findMember((string) $request->input('documentable_id', ''));
            Gate::authorize('createPersonnelFile', [Document::class, $member]);

            $data = $request->validate(PersonnelFileService::rules(includeFile: true));
            /** @var User $creator */
            $creator = Auth::user();
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $this->service->assertAllowedFile($file);

            $document = $this->personnelFiles->create($member, $creator, $data, $file);

            return redirect()
                ->back()
                ->with('success', __('hr.personnel_file.flash.created'))
                ->withFragment('document-' . $document->id);
        }

        Gate::authorize('create', Document::class);

        $data = $this->validateDocument($request, includeFile: true);

        $documentable = null;
        if (filled($data['documentable_kind'] ?? null)) {
            $documentable = $this->findDocumentable((string) $data['documentable_kind'], (string) ($data['documentable_id'] ?? ''));
        }

        /** @var User $creator */
        $creator = Auth::user();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->service->assertAllowedFile($file);

        $document = $this->service->create($documentable, $creator, $data, $file);

        return redirect()
            ->back()
            ->with('success', __('document.flash.created'))
            ->withFragment('document-' . $document->id);
    }

    public function edit(Document $document): View {
        Gate::authorize('update', $document);

        if ($document->isPersonnelFile()) {
            return view('hr.personnel-file._form_dialog', ['member' => $document->documentable, 'document' => $document]);
        }

        return view('documents._form_dialog', [
            'document' => $document,
            'documentableKind' => $this->kindFor($document),
            'documentableId' => null,
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse {
        Gate::authorize('update', $document);

        /** @var User $actor */
        $actor = Auth::user();

        if ($document->isPersonnelFile()) {
            $data = $request->validate(PersonnelFileService::rules(includeFile: false));
            $this->personnelFiles->update($document, $actor, $data);

            return redirect()
                ->back()
                ->with('success', __('hr.personnel_file.flash.updated'))
                ->withFragment('document-' . $document->id);
        }

        $data = $this->validateDocument($request, includeFile: false);
        $this->service->update($document, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('document.flash.updated'))
            ->withFragment('document-' . $document->id);
    }

    /**
     * Gibt das Dokument fürs Kundenportal frei (Welle D). Nur kunden-/
     * auftragsgebundene Dokumente sind freigebbar — ein freies/internes
     * Dokument lässt sich keinem Portal-Kunden zuordnen.
     */
    public function release(Document $document): RedirectResponse {
        Gate::authorize('releaseToCustomer', $document);

        $document->loadMissing('documentable');
        if (! $document->isReleasableToCustomer()) {
            return redirect()->back()->with('error', __('document.customer.error.not_linked'));
        }

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->releaseToCustomer($document, $actor);

        return redirect()
            ->back()
            ->with('success', __('document.customer.flash.released'))
            ->withFragment('document-' . $document->id);
    }

    /** Zieht die Kundenfreigabe zurück (Welle D). */
    public function revoke(Document $document): RedirectResponse {
        Gate::authorize('releaseToCustomer', $document);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->revokeFromCustomer($document, $actor);

        return redirect()
            ->back()
            ->with('success', __('document.customer.flash.revoked'))
            ->withFragment('document-' . $document->id);
    }

    /** Versions-Dialog: Historie + Upload-Formular für eine neue Version. */
    public function versions(Document $document): View {
        Gate::authorize('view', $document);

        return view('documents._version_dialog', [
            'document' => $document->load(['versions.uploader', 'currentVersion']),
            'canAddVersion' => Gate::allows('addVersion', $document),
        ]);
    }

    public function addVersion(Request $request, Document $document): RedirectResponse {
        Gate::authorize('addVersion', $document);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . FileAttacher::maxKb()],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->service->assertAllowedFile($file);

        $version = $this->service->addVersion($document, $actor, $file, $data['note'] ?? null);

        return redirect()
            ->back()
            ->with('success', __('document.flash.version_added', ['no' => $version->version_no]))
            ->withFragment('document-' . $document->id);
    }

    /**
     * Download der aktuellen oder einer spezifischen Version. Pfade kommen
     * ausschließlich aus der DB (UUID-Dateinamen aus dem Upload-Pfad) und
     * werden über den Storage-Disk aufgelöst — kein Client-Input im Pfad,
     * Pfad-Traversal ausgeschlossen (vgl. ../WorkDiary-Architecture/security/adr-attachment-paths.md).
     */
    public function download(Document $document, ?DocumentVersion $version = null): BinaryFileResponse {
        Gate::authorize('view', $document);

        $this->auditConfidentialAccess($document);

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

        // Personalakte (Feature 141): jeder Abruf wird auditiert — auch der eigene.
        if ($document->isPersonnelFile()) {
            $document->audit('hrFile.downloaded', [
                'version_no' => $version->version_no,
                'original_name' => $version->original_name,
                'member_user_id' => $document->documentable_id,
            ]);
        }

        return response()->download($disk->path($version->path), $version->original_name);
    }

    public function archive(Document $document): RedirectResponse {
        Gate::authorize('archive', $document);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->archive($document, $actor);

        return redirect()
            ->back()
            ->with('success', __('document.flash.archived'));
    }

    public function destroy(Document $document): RedirectResponse {
        Gate::authorize('delete', $document);

        /** @var User $actor */
        $actor = Auth::user();
        if ($document->isPersonnelFile()) {
            // Personalakte (Feature 141): Vernichtung statt Papierkorb.
            $this->personnelFiles->destroy($document, $actor, 'manual');
        } else {
            $this->service->delete($document, $actor);
        }

        return redirect()
            ->back()
            ->with('success', __('document.flash.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocument(Request $request, bool $includeFile): array {
        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'document_type' => ['required', 'string', 'in:' . implode(',', array_column(DocumentType::cases(), 'value'))],
            'status' => ['nullable', 'string', 'in:' . DocumentStatus::Draft->value . ',' . DocumentStatus::Active->value],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'description' => ['nullable', 'string', 'max:4000'],
            // Vertraulichkeitsmerkmal (Vollaudit 2026-07, N10).
            'confidential' => ['nullable', 'boolean'],
        ];

        if ($includeFile) {
            $rules['file'] = ['required', 'file', 'max:' . FileAttacher::maxKb()];
            $rules['version_note'] = ['nullable', 'string', 'max:500'];
            $rules['documentable_kind'] = ['nullable', 'string', 'in:' . implode(',', array_keys(self::DOCUMENTABLE_MAP))];
            $rules['documentable_id'] = ['nullable', 'string', 'required_with:documentable_kind'];
        }

        return $request->validate($rules);
    }

    /**
     * Fremdzugriff auf vertrauliche Dokumente auditieren (Vollaudit 2026-07,
     * N10): greift, wenn ein Verwalter (document.confidential.manage/Admin)
     * ein vertrauliches Dokument eines anderen Erfassers öffnet/herunterlädt.
     */
    private function auditConfidentialAccess(Document $document): void {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null || ! $document->confidential || (int) $document->created_by_user_id === (int) $user->id) {
            return;
        }
        // Eigenauskunft der Personalakte (Feature 141) ist kein Fremdzugriff.
        if ($document->isPersonnelFile() && (int) $document->documentable_id === (int) $user->id) {
            return;
        }

        \App\Models\AuditLog::query()->create([
            'organization_id' => $document->organization_id,
            'user_id' => $user->id,
            'event' => 'document.confidentialAccessed',
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
            'changes' => ['title' => $document->title],
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveOptionalDocumentableFromRequest(Request $request): array {
        $kind = (string) $request->query('documentable_kind', '');
        if ($kind === '') {
            return [null, null];
        }
        if (! array_key_exists($kind, self::DOCUMENTABLE_MAP)) {
            abort(404);
        }

        $documentable = $this->findDocumentable($kind, (string) $request->query('documentable_id', ''));

        return [$kind, Sqid::encode($documentable::class, (int) $documentable->getKey())];
    }

    private function findDocumentable(string $kind, string $rawId): Model {
        $class = self::DOCUMENTABLE_MAP[$kind] ?? null;
        if ($class === null) {
            abort(404);
        }

        $id = Sqid::decodeOrNumeric($class, $rawId);
        if ($id === null || $id < 1) {
            abort(404);
        }

        $query = $class::query();
        if ($class === User::class) {
            // users trägt keinen globalen Org-Scope — Mandantengrenze explizit.
            /** @var User|null $auth */
            $auth = Auth::user();
            $query->where('organization_id', $auth?->organization_id);
        }

        /** @var Model|null $documentable */
        $documentable = $query->find($id);
        if ($documentable === null) {
            abort(404);
        }

        return $documentable;
    }

    /** Mitglied der eigenen Org für die Personalakte (Feature 141). */
    private function findMember(string $rawId): User {
        $member = $this->findDocumentable('user', $rawId);
        if (! $member instanceof User) {
            abort(404);
        }

        return $member;
    }

    private function kindFor(Document $document): ?string {
        if ($document->documentable_type === null) {
            return null;
        }

        return array_search($document->documentable_type, self::DOCUMENTABLE_MAP, true) ?: null;
    }

    /**
     * @param  Builder<Document>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void {
        match ($status) {
            DocumentStatus::Expired->value => $query->expired(),
            DocumentStatus::Active->value => $query->active(),
            DocumentStatus::Draft->value, DocumentStatus::Archived->value => $query->where('status', $status),
            default => null,
        };
    }

    /**
     * @param  Builder<Document>  $query
     */
    private function applyRefFilter(Builder $query, string $ref): void {
        if ($ref === 'none') {
            $query->whereNull('documentable_type');

            return;
        }

        $class = self::DOCUMENTABLE_MAP[$ref] ?? null;
        if ($class !== null) {
            $query->where('documentable_type', $class);
        }
    }
}
