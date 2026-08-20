<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JointControllerAgreementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\AgreementStatus;
use App\Http\Controllers\Controller;
use App\Models\Privacy\{JointControllerAgreement, ProcessingActivity, Processor};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Vereinbarungen gemeinsam Verantwortlicher (GVV, Art. 26) mit Zuständigkeitsmatrix. */
class JointControllerAgreementController extends Controller {
    /** @var list<string> */
    private const MATRIX_KEYS = ['information_duties', 'data_subject_rights', 'incidents', 'authority_contact'];

    public function index(): View {
        Gate::authorize('viewAny', JointControllerAgreement::class);

        return view('privacy.gvv.index', [
            'agreements' => JointControllerAgreement::query()->with('partner')->latest('id')->paginate(20),
            'partners' => Processor::query()->orderBy('name')->get(['id', 'name']),
            'matrixKeys' => self::MATRIX_KEYS,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', JointControllerAgreement::class);
        $user = $request->user();
        $org = $user?->organization;
        abort_unless($org !== null, 403);

        // Sqid-Input dekodieren (numerischer Fallback für Alt-Clients).
        $request->merge(['partner_id' => Sqid::decodeOrNumeric(Processor::class, $request->input('partner_id'))]);

        $data = $request->validate([
            'partner_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:32'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'contact_point' => ['nullable', 'string', 'max:255'],
            'essence_provided' => ['nullable', 'boolean'],
            'responsibilities' => ['array'],
            'responsibilities.*' => ['in:us,partner,joint'],
            'document' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx'],
        ]);

        $partner = Processor::query()->where('organization_id', $org->id)->findOrFail((int) $data['partner_id']);

        $gvv = new JointControllerAgreement($data);
        $gvv->setAttribute('organization_id', $org->id);
        $gvv->setAttribute('partner_id', $partner->id);
        $gvv->setAttribute('created_by', $user->id);
        $gvv->responsibilities = $this->matrix($request);
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $stored = $file->store('privacy/gvv', 'local');
            if ($stored !== false) {
                $gvv->setAttribute('document_path', $stored);
                $gvv->setAttribute('document_name', \App\Support\Filename::sanitize($file->getClientOriginalName()));
            }
        }
        $gvv->save();

        return redirect()->route('dataprotection.gvv.show', $gvv)->with('status', __('GVV angelegt.'));
    }

    public function show(JointControllerAgreement $gvv): View {
        Gate::authorize('view', $gvv);

        return view('privacy.gvv.show', [
            'gvv' => $gvv->load(['partner', 'activities']),
            'allActivities' => ProcessingActivity::query()->orderBy('name')->get(['id', 'name']),
            'linkedIds' => $gvv->activities()->pluck('privacy_processing_activities.id')->all(),
            'matrixKeys' => self::MATRIX_KEYS,
        ]);
    }

    public function update(Request $request, JointControllerAgreement $gvv): RedirectResponse {
        Gate::authorize('update', $gvv);
        $request->validate([
            'contact_point' => ['nullable', 'string', 'max:255'],
            'essence_provided' => ['nullable', 'boolean'],
            'responsibilities' => ['array'],
            'responsibilities.*' => ['in:us,partner,joint'],
            'status' => ['nullable', 'in:draft,active,terminated,expired'],
        ]);

        $gvv->responsibilities = $this->matrix($request);
        $gvv->forceFill([
            'contact_point' => $request->string('contact_point')->toString() ?: null,
            'essence_provided' => $request->boolean('essence_provided'),
            'status' => $request->filled('status') ? AgreementStatus::from((string) $request->input('status')) : $gvv->status,
        ])->save();

        return back()->with('status', __('GVV aktualisiert.'));
    }

    public function syncActivities(Request $request, JointControllerAgreement $gvv): RedirectResponse {
        Gate::authorize('update', $gvv);
        // Sqids aus dem Formular (Audit 2026-08, W3.3); die org-gescopte
        // Whitelist darunter bleibt die eigentliche Schutzlinie.
        $data = $request->validate(['activity_ids' => ['array'], 'activity_ids.*' => ['string']]);
        $requested = array_filter(array_map(
            static fn (string $v): ?int => \App\Support\Sqid::decodeOrNumeric(ProcessingActivity::class, $v),
            $data['activity_ids'] ?? [],
        ));
        $valid = ProcessingActivity::query()
            ->where('organization_id', $gvv->organization_id)
            ->whereIn('id', $requested)
            ->pluck('id')->all();
        $gvv->activities()->sync($valid);

        return back()->with('status', __('Verknüpfungen gespeichert.'));
    }

    public function downloadDocument(JointControllerAgreement $gvv): BinaryFileResponse {
        Gate::authorize('view', $gvv);
        $path = $gvv->document_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path), $gvv->document_name ?? 'gvv.pdf');
    }

    /** @return array<string, string> */
    private function matrix(Request $request): array {
        /** @var array<string, mixed> $input */
        $input = (array) $request->input('responsibilities', []);
        $matrix = [];
        foreach (self::MATRIX_KEYS as $key) {
            $value = $input[$key] ?? 'joint';
            $matrix[$key] = in_array($value, ['us', 'partner', 'joint'], true) ? (string) $value : 'joint';
        }

        return $matrix;
    }
}
