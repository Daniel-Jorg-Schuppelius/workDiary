<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\ProcedureDocumentationStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Finance\ProcedureDocumentation;
use App\Services\Finance\ProcedureDocumentation\{ProcedureDocumentationComparison, ProcedureDocumentationService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * GoBD-Verfahrensdokumentation (Feature 134, MVP-699): Versionsliste,
 * Entwurf (Freitext-Dialog), Live-Vorschau des generierten Systemteils,
 * Veröffentlichen (Snapshot + PDF + Hash) und PDF-Download. Recht
 * `finance.gobd.export` über die Policy; Modul-Gating `module.finance`
 * automatisch über die `finance.*`-Routen.
 */
class ProcedureDocumentationController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly ProcedureDocumentationService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', ProcedureDocumentation::class);
        $organization = $this->currentOrganization();

        $documents = ProcedureDocumentation::query()
            ->where('organization_id', $organization->id)
            ->with(['createdBy:id,name', 'publishedBy:id,name'])
            ->orderByDesc('version')
            ->paginate(25)
            ->withQueryString();

        return view('finance.procedure-documentation.index', [
            'documents' => $documents,
            'hasDraft' => ProcedureDocumentation::query()->where('organization_id', $organization->id)->whereIn('status', [ProcedureDocumentationStatus::Draft->value, ProcedureDocumentationStatus::InReview->value])->exists(),
            'canManage' => Gate::allows('create', ProcedureDocumentation::class),
        ]);
    }

    /** Neuen Entwurf anlegen (Freitexte aus der letzten Veröffentlichung vorbelegt). */
    public function store(): RedirectResponse {
        Gate::authorize('create', ProcedureDocumentation::class);

        $document = $this->service->createDraft($this->currentOrganization(), Auth::user());

        return redirect()->route('finance.procedure-documentation.show', $document)
            ->with('status', __('procedure-documentation.flash.draft_created', ['version' => $document->displayVersion()]));
    }

    public function show(ProcedureDocumentation $document): View {
        Gate::authorize('view', $document);

        // Veröffentlicht: der eingefrorene Snapshot; Entwurf: Live-Vorschau.
        $payload = $document->isPublished()
            ? (array) $document->snapshot
            : $this->service->preview($this->currentOrganization());

        return view('finance.procedure-documentation.show', [
            'document' => $document->loadMissing('submitter:id,name'),
            'payload' => $payload,
            'canManage' => Gate::allows('update', $document),
            'canPublish' => Gate::allows('publish', $document),
            'requiresReview' => $this->service->requiresReview(),
            'others' => ProcedureDocumentation::query()->where('organization_id', $document->organization_id)->whereKeyNot($document->id)->orderByDesc('version')->get(['id', 'version', 'status']),
        ]);
    }

    /** Zur Freigabe vorlegen (MVP-995). */
    public function submit(ProcedureDocumentation $document): RedirectResponse {
        Gate::authorize('update', $document);
        /** @var \App\Models\Platform\User $user */
        $user = Auth::user();
        $this->service->submit($document, $user);

        return redirect()->route('finance.procedure-documentation.show', $document)->with('status', __('procedure-documentation.flash.submitted'));
    }

    public function rejectForm(ProcedureDocumentation $document): View {
        Gate::authorize('publish', $document);

        return view('finance.procedure-documentation._reject_dialog', ['document' => $document]);
    }

    public function reject(Request $request, ProcedureDocumentation $document): RedirectResponse {
        Gate::authorize('publish', $document);
        $data = $request->validate(['review_note' => ['required', 'string', 'min:3', 'max:500']]);
        /** @var \App\Models\Platform\User $user */
        $user = Auth::user();
        $this->service->reject($document, $user, (string) $data['review_note']);

        return redirect()->route('finance.procedure-documentation.show', $document)->with('status', __('procedure-documentation.flash.rejected'));
    }

    /** Abschnittsvergleich mit einer anderen Fassung (MVP-995); die ältere steht links. */
    public function compare(Request $request, ProcedureDocumentation $document, ProcedureDocumentationComparison $comparison): View {
        Gate::authorize('view', $document);
        $other = ProcedureDocumentation::query()->where('organization_id', $document->organization_id)
            ->findOrFail(Sqid::decodeOrNumeric(ProcedureDocumentation::class, $request->string('with')->toString()));
        [$old, $new] = $other->version < $document->version ? [$other, $document] : [$document, $other];

        return view('finance.procedure-documentation.compare', ['old' => $old, 'new' => $new, 'rows' => $comparison->compare($old, $new), 'document' => $document]);
    }

    /** Freitext-Dialog; wird per data-entry-modal-trigger geladen. */
    public function edit(ProcedureDocumentation $document): View {
        Gate::authorize('update', $document);
        abort_unless($document->isEditable(), 404);

        return view('finance.procedure-documentation._form_dialog', ['document' => $document]);
    }

    public function update(Request $request, ProcedureDocumentation $document): RedirectResponse {
        Gate::authorize('update', $document);

        $rules = [];
        foreach (ProcedureDocumentation::TEXT_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:20000'];
        }
        $data = $request->validate($rules);

        $this->service->update($document, $data);

        return redirect()->route('finance.procedure-documentation.show', $document)
            ->with('status', __('procedure-documentation.flash.updated'));
    }

    public function publish(ProcedureDocumentation $document): RedirectResponse {
        Gate::authorize('publish', $document);

        $document = $this->service->publish($document, Auth::user());

        return redirect()->route('finance.procedure-documentation.show', $document)
            ->with('status', __('procedure-documentation.flash.published', ['version' => $document->displayVersion()]));
    }

    public function download(ProcedureDocumentation $document): Response {
        Gate::authorize('view', $document);
        abort_unless($document->isPublished(), 404);

        $bytes = $this->service->pdfBytes($document);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="verfahrensdokumentation-' . $document->displayVersion() . '.pdf"',
        ]);
    }

    /** Entwurf verwerfen — veröffentlichte Versionen sind unlöschbar (Modell-Guard). */
    public function destroy(ProcedureDocumentation $document): RedirectResponse {
        Gate::authorize('delete', $document);
        abort_unless($document->isEditable(), 404);

        $document->delete();

        return redirect()->route('finance.procedure-documentation.index')
            ->with('status', __('procedure-documentation.flash.deleted'));
    }
}
