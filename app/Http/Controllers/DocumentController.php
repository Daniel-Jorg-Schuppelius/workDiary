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
use App\Services\Document\DocumentService;
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
    ];

    private const MAX_BYTES = 25 * 1024 * 1024; // 25 MB (wie AttachmentController)

    /** @var array<int, string> Erlaubte Datei-Endungen (analog AttachmentController). */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'log', 'zip', 'docx', 'xlsx'];

    /** @var array<int, string> Serverseitig akzeptierte MIME-Typen (PHP Fileinfo, nicht Client-Header). */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-zip-compressed',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(
        private readonly DocumentService $service,
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

        $query = Document::query()
            ->with(['currentVersion', 'documentable', 'creator'])
            ->latest('updated_at');

        if ($filters['q'] !== '') {
            $query->where('title', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%');
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

    public function create(Request $request): View {
        Gate::authorize('create', Document::class);

        [$documentableKind, $documentableId] = $this->resolveOptionalDocumentableFromRequest($request);

        return view('documents._form_dialog', [
            'document' => null,
            'documentableKind' => $documentableKind,
            'documentableId' => $documentableId,
        ]);
    }

    public function store(Request $request): RedirectResponse {
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
        $this->assertAllowedFile($file);

        $document = $this->service->create($documentable, $creator, $data, $file);

        return redirect()
            ->back()
            ->with('success', __('document.flash.created'))
            ->withFragment('document-' . $document->id);
    }

    public function edit(Document $document): View {
        Gate::authorize('update', $document);

        return view('documents._form_dialog', [
            'document' => $document,
            'documentableKind' => $this->kindFor($document),
            'documentableId' => null,
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse {
        Gate::authorize('update', $document);

        $data = $this->validateDocument($request, includeFile: false);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->update($document, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('document.flash.updated'))
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
            'file' => ['required', 'file', 'max:' . (self::MAX_BYTES / 1024)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->assertAllowedFile($file);

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
     * Pfad-Traversal ausgeschlossen (vgl. docs/security/adr-attachment-paths.md).
     */
    public function download(Document $document, ?DocumentVersion $version = null): BinaryFileResponse {
        Gate::authorize('view', $document);

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
        $this->service->delete($document, $actor);

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
        ];

        if ($includeFile) {
            $rules['file'] = ['required', 'file', 'max:' . (self::MAX_BYTES / 1024)];
            $rules['version_note'] = ['nullable', 'string', 'max:500'];
            $rules['documentable_kind'] = ['nullable', 'string', 'in:' . implode(',', array_keys(self::DOCUMENTABLE_MAP))];
            $rules['documentable_id'] = ['nullable', 'string', 'required_with:documentable_kind'];
        }

        return $request->validate($rules);
    }

    /** Erweiterungs- und Server-MIME-Prüfung analog AttachmentController. */
    private function assertAllowedFile(UploadedFile $file): void {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $serverMime = $file->getMimeType() ?? '';
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true) || ! in_array($serverMime, self::ALLOWED_MIMES, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => (string) __('Dateityp nicht erlaubt.'),
            ]);
        }
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

        $id = Sqid::decode($class, $rawId);
        if ($id === null && is_numeric($rawId)) {
            $id = (int) $rawId;
        }
        if ($id === null || $id < 1) {
            abort(404);
        }

        /** @var Model|null $documentable */
        $documentable = $class::query()->find($id);
        if ($documentable === null) {
            abort(404);
        }

        return $documentable;
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
