<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermitController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Permit\PermitStatus;
use App\Http\Controllers\Concerns\ParsesIndexQuery;
use App\Http\Requests\SavePermitRequest;
use App\Models\{Event, Permit, User};
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\View\View;

class PermitController extends Controller {
    use ParsesIndexQuery;

    private const ALLOWED_SORTS = ['title', 'authority', 'status', 'valid_until'];

    /** Nachweis-Dokument: erlaubte Endungen/MIME (Teilmenge des AttachmentControllers). */
    private const EVIDENCE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];

    private const EVIDENCE_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const EVIDENCE_MAX_BYTES = 25 * 1024 * 1024; // 25 MB

    public function index(Request $request): View {
        Gate::authorize('viewAny', Permit::class);

        $query = trim($request->string('q')->toString());
        $statusFilter = $this->normalizeStatus($request->string('status')->toString());
        ['sort' => $sort, 'dir' => $dir] = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'valid_until');

        $permitsQuery = Permit::query()
            ->with('event')
            ->orderBy($sort, $dir);

        if ($query !== '') {
            $permitsQuery->search($query);
        }

        if ($statusFilter !== null) {
            $permitsQuery->where('status', $statusFilter);
        }

        return view('permits.index', [
            'permits' => $permitsQuery->paginate(20)->withQueryString(),
            'statusOptions' => $this->statusOptions(),
            'activeFilters' => [
                'q' => $query,
                'status' => $statusFilter ?? 'all',
            ],
            'sort' => $sort,
            'dir' => $dir,
            'canCreate' => Gate::allows('create', Permit::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Permit::class);

        return view('permits._form_dialog', [
            'permit' => new Permit(['status' => PermitStatus::Required->value]),
            'statusOptions' => $this->statusOptions(),
            'eventOptions' => $this->eventOptions(),
        ]);
    }

    public function store(SavePermitRequest $request): RedirectResponse {
        Gate::authorize('create', Permit::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $payload = $request->validated();
        $payload['organization_id'] = (int) $user->organization_id;
        $payload['created_by'] = $user->id;

        $permit = Permit::query()->create($payload);

        $error = $this->storeEvidence($permit, $request);
        if ($error !== null) {
            return back()->withErrors(['evidence_document' => $error])->withInput();
        }

        return redirect()->route('permits.index')->with('success', __('permit.messages.created'));
    }

    public function edit(Permit $permit): View {
        Gate::authorize('update', $permit);

        return view('permits._form_dialog', [
            'permit' => $permit,
            'statusOptions' => $this->statusOptions(),
            'eventOptions' => $this->eventOptions(),
        ]);
    }

    public function update(SavePermitRequest $request, Permit $permit): RedirectResponse {
        Gate::authorize('update', $permit);
        $user = $request->user();

        $payload = $request->validated();
        if ($user instanceof User) {
            $payload['updated_by'] = $user->id;
        }
        $permit->update($payload);

        $error = $this->storeEvidence($permit, $request);
        if ($error !== null) {
            return back()->withErrors(['evidence_document' => $error])->withInput();
        }

        return redirect()->route('permits.index')->with('success', __('permit.messages.updated'));
    }

    public function destroy(Permit $permit): RedirectResponse {
        Gate::authorize('delete', $permit);

        $permit->delete();

        return redirect()->route('permits.index')->with('success', __('permit.messages.deleted'));
    }

    /**
     * Speichert ein hochgeladenes Nachweis-Dokument als Anhang (meta_type=evidence)
     * und ersetzt ein vorhandenes. Gibt eine Fehlermeldung zurück oder null bei Erfolg
     * bzw. wenn keine Datei gesendet wurde.
     */
    private function storeEvidence(Permit $permit, Request $request): ?string {
        $file = $request->file('evidence_document');
        if (! $file instanceof UploadedFile) {
            return null;
        }

        if ($file->getSize() > self::EVIDENCE_MAX_BYTES) {
            return __('permit.evidence.too_large');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
        $mime = $file->getMimeType() ?? '';
        if (! in_array($ext, self::EVIDENCE_EXTENSIONS, true) || ! in_array($mime, self::EVIDENCE_MIMES, true)) {
            return __('permit.evidence.invalid_type');
        }

        // Vorhandenen Nachweis ersetzen (Datei + Datensatz).
        $existing = $permit->evidence();
        if ($existing !== null) {
            Storage::disk($existing->disk)->delete($existing->path);
            $existing->delete();
        }

        $folder = 'attachments/' . now()->format('Y/m');
        $path = $file->storeAs($folder, Str::uuid()->toString() . '.' . $ext, 'local');

        $permit->attachments()->create([
            'organization_id' => $permit->organization_id,
            'user_id' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime' => $mime,
            'size' => $file->getSize(),
            'meta_type' => Permit::EVIDENCE_META,
        ]);

        return null;
    }

    private function normalizeStatus(string $value): ?string {
        return PermitStatus::tryFrom($value)?->value;
    }

    /** @return array<string, string> */
    private function statusOptions(): array {
        $out = [];
        foreach (PermitStatus::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /**
     * Events der aktiven Organisation für die optionale Verknüpfung.
     *
     * @return \Illuminate\Support\Collection<int, Event>
     */
    private function eventOptions(): \Illuminate\Support\Collection {
        return Event::query()
            ->orderByDesc('started_at')
            ->limit(200)
            ->get(['id', 'title', 'started_at']);
    }
}
