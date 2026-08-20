<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingAgreementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\{ProcessingActivity, ProcessingAgreement, Processor, Subprocessor};
use App\Services\Privacy\AgreementService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Auftragsverarbeitungsvertraege (Art. 28): Anlage, Lebenszyklus, Vertragsende-
 * Nachweis, Unterauftragsverarbeiter und Verknuepfung zu Verarbeitungstaetigkeiten.
 */
class ProcessingAgreementController extends Controller {
    public function __construct(private readonly AgreementService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', ProcessingAgreement::class);

        return view('privacy.agreements.index', [
            'agreements' => ProcessingAgreement::query()->with('processor')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ProcessingAgreement::class);
        $user = $request->user();
        $org = $user?->organization;
        abort_unless($org !== null, 403);

        $data = $request->validate([
            'processor_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:32'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'review_due_at' => ['nullable', 'date'],
            'data_categories' => ['nullable', 'string', 'max:5000'],
            'tom_checked' => ['nullable', 'boolean'],
            'document' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx'],
        ]);

        // Dienstleister muss zur Org gehoeren.
        // Sqid aus dem Formular (W3.3); die org-gescopte Query bleibt die Schutzlinie.
        $processor = Processor::query()->where('organization_id', $org->id)
            ->findOrFail(\App\Support\Sqid::decodeOrAbort(Processor::class, (string) $data['processor_id']));

        $agreement = new ProcessingAgreement($data);
        $agreement->setAttribute('organization_id', $org->id);
        $agreement->setAttribute('processor_id', $processor->id);
        $agreement->setAttribute('created_by', $user->id);
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $stored = $file->store('privacy/agreements', 'local');
            if ($stored !== false) {
                $agreement->setAttribute('document_path', $stored);
                $agreement->setAttribute('document_name', \App\Support\Filename::sanitize($file->getClientOriginalName()));
            }
        }
        $agreement->save();

        return redirect()->route('dataprotection.agreements.show', $agreement)
            ->with('status', __('AVV angelegt.'));
    }

    public function show(ProcessingAgreement $agreement): View {
        Gate::authorize('view', $agreement);

        $assignedMeasureIds = \App\Models\Privacy\MeasureAssignment::query()
            ->where('agreement_id', $agreement->id)->pluck('measure_id')->all();

        return view('privacy.agreements.show', [
            'agreement' => $agreement->load(['processor', 'subprocessors', 'activities']),
            'allActivities' => ProcessingActivity::query()->orderBy('name')->get(['id', 'name']),
            'linkedIds' => $agreement->activities()->pluck('privacy_processing_activities.id')->all(),
            'allMeasures' => \App\Models\Privacy\TechnicalMeasure::query()->orderBy('name')->get(['id', 'name']),
            'assignedMeasures' => \App\Models\Privacy\TechnicalMeasure::query()->whereIn('id', $assignedMeasureIds)->get(['id', 'name']),
        ]);
    }

    public function activate(ProcessingAgreement $agreement): RedirectResponse {
        Gate::authorize('update', $agreement);
        $this->service->activate($agreement);

        return back()->with('status', __('AVV aktiviert.'));
    }

    public function terminate(ProcessingAgreement $agreement): RedirectResponse {
        Gate::authorize('update', $agreement);
        $this->service->terminate($agreement);

        return back()->with('status', __('AVV gekündigt – Datenrückgabe/Löschung offen.'));
    }

    public function confirmReturn(Request $request, ProcessingAgreement $agreement): RedirectResponse {
        Gate::authorize('update', $agreement);
        $data = $request->validate(['mode' => ['required', 'in:returned,deleted']]);
        $this->service->confirmDataReturn($agreement, $data['mode']);

        return back()->with('status', __('Datenrückgabe/Löschung bestätigt.'));
    }

    public function syncActivities(Request $request, ProcessingAgreement $agreement): RedirectResponse {
        Gate::authorize('update', $agreement);
        // Sqids aus dem Formular (Audit 2026-08, W3.3).
        $data = $request->validate(['activity_ids' => ['array'], 'activity_ids.*' => ['string']]);
        $requested = array_filter(array_map(
            static fn (string $v): ?int => \App\Support\Sqid::decodeOrNumeric(ProcessingActivity::class, $v),
            $data['activity_ids'] ?? [],
        ));

        // Nur Taetigkeiten der eigenen Org verknuepfen.
        $valid = ProcessingActivity::query()
            ->where('organization_id', $agreement->organization_id)
            ->whereIn('id', $requested)
            ->pluck('id')->all();
        $agreement->activities()->sync($valid);

        return back()->with('status', __('Verknüpfungen gespeichert.'));
    }

    public function storeSubprocessor(Request $request, ProcessingAgreement $agreement): RedirectResponse {
        Gate::authorize('update', $agreement);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'third_country' => ['nullable', 'boolean'],
            'safeguards' => ['nullable', 'string', 'max:255'],
        ]);

        Subprocessor::create([
            'organization_id' => $agreement->organization_id,
            'agreement_id' => $agreement->id,
            'added_at' => now(),
            ...$data,
        ]);

        return back()->with('status', __('Unterauftragsverarbeiter hinzugefügt (zur Freigabe).'));
    }

    public function approveSubprocessor(ProcessingAgreement $agreement, Subprocessor $subprocessor): RedirectResponse {
        Gate::authorize('update', $agreement);
        abort_unless((int) $subprocessor->agreement_id === (int) $agreement->id, 404);
        $subprocessor->forceFill(['approved' => true])->save();

        return back()->with('status', __('Unterauftragsverarbeiter freigegeben.'));
    }

    public function destroySubprocessor(ProcessingAgreement $agreement, Subprocessor $subprocessor): RedirectResponse {
        Gate::authorize('update', $agreement);
        abort_unless((int) $subprocessor->agreement_id === (int) $agreement->id, 404);
        $subprocessor->delete();

        return back()->with('status', __('Unterauftragsverarbeiter entfernt.'));
    }

    public function assignMeasure(Request $request, ProcessingAgreement $agreement): RedirectResponse {
        Gate::authorize('update', $agreement);
        // Sqid-Input dekodieren (numerischer Fallback für Alt-Clients).
        $request->merge(['measure_id' => Sqid::decodeOrNumeric(\App\Models\Privacy\TechnicalMeasure::class, $request->input('measure_id'))]);
        $data = $request->validate(['measure_id' => ['required', 'integer']]);
        $measure = \App\Models\Privacy\TechnicalMeasure::query()
            ->where('organization_id', $agreement->organization_id)
            ->findOrFail((int) $data['measure_id']);
        app(\App\Services\Privacy\TechnicalMeasureService::class)->assignToAgreement($measure, $agreement);

        return back()->with('status', __('TOM dem Vertrag zugeordnet.'));
    }

    public function downloadDocument(ProcessingAgreement $agreement): BinaryFileResponse {
        Gate::authorize('view', $agreement);
        $path = $agreement->document_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path), $agreement->document_name ?? 'avv.pdf');
    }
}
