<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNoticeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Construction\ConstructionNoticeStatus;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SaveConstructionNoticeRequest;
use App\Models\Construction\ConstructionNotice;
use App\Models\{Customer, DiaryEntry, Project, Site};
use App\Services\Construction\{ConstructionNoticePdfRenderer, ConstructionNoticeService};
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * VOB/B-Schreiben (Feature 062, MVP-728, H23): Behinderungsanzeige (§ 6 VOB/B)
 * und Bedenkenanmeldung (§ 4 Abs. 3 VOB/B).
 *
 * Berechtigung wie bei den Gewaehrleistungsfristen (Feature 115) ueber die
 * Projektrechte — Bauunterlagen haben keine eigene Rechteinsel; das harte
 * Modul-Gate (`construction-notices.*` → module.bau) sperrt zusaetzlich.
 *
 * Ein versendetes Schreiben ist festgeschrieben: Bearbeiten und Loeschen
 * enden dann in 422 ({@see ConstructionNoticeService::assertEditable()}).
 */
class ConstructionNoticeController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly ConstructionNoticeService $notices) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Project::class);

        $filters = [
            'kind' => (string) $request->query('kind', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $query = ConstructionNotice::query()->with(['customer:id,name,company', 'project:id,name', 'site:id,name', 'diaryEntry:id,title']);
        $kind = RenderDocumentKind::tryFrom($filters['kind']);
        if ($kind !== null && in_array($kind, ConstructionNotice::KINDS, true)) {
            $query->where('kind', $kind->value);
        }
        if (ConstructionNoticeStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        return view('construction-notices.index', [
            'notices' => $query->orderByDesc('occurred_on')->orderByDesc('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'kinds' => ConstructionNotice::KINDS,
        ]);
    }

    public function form(Request $request, ?ConstructionNotice $notice = null): View {
        Gate::authorize('viewAny', Project::class);
        if ($notice !== null) {
            $this->notices->assertEditable($notice);
        }

        $kind = $notice !== null ? $notice->kind : $this->kindFromRequest($request);
        $organizationId = (int) $this->currentOrganization()->id;

        return view('construction-notices._form_dialog', [
            'notice' => $notice,
            'kind' => $kind,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'company']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->where('organization_id', $organizationId)->orderBy('name')->get(['id', 'name']),
            'diaryEntries' => DiaryEntry::query()->orderByDesc('start_at')->limit(200)->get(['id', 'title', 'start_at']),
        ]);
    }

    public function store(SaveConstructionNoticeRequest $request): RedirectResponse {
        Gate::authorize('create', Project::class);

        $notice = $this->notices->create(
            $this->currentOrganization(),
            $this->kindFromRequest($request),
            $request->validated(),
            $request->user(),
        );

        return redirect()->route('construction-notices.show', $notice)->with('status', __('construction.created'));
    }

    public function show(ConstructionNotice $notice): View {
        Gate::authorize('viewAny', Project::class);

        return view('construction-notices.show', [
            'notice' => $notice->load(['customer', 'project', 'site', 'diaryEntry', 'weatherSnapshot', 'creator']),
            'dispatches' => $notice->dispatches()->get(),
            'deliveryMethods' => ConstructionNoticeService::DELIVERY_METHODS,
        ]);
    }

    public function update(SaveConstructionNoticeRequest $request, ConstructionNotice $notice): RedirectResponse {
        Gate::authorize('create', Project::class);
        $this->notices->update($notice, $request->validated(), $request->user());

        return redirect()->route('construction-notices.show', $notice)->with('status', __('construction.updated'));
    }

    public function pdf(ConstructionNotice $notice): Response {
        Gate::authorize('viewAny', Project::class);

        $renderer = app(ConstructionNoticePdfRenderer::class);

        return response($renderer->render($notice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($notice) . '.pdf"',
        ]);
    }

    /** Zugangsnachweis ausserhalb der E-Mail (Einschreiben, Bote, Uebergabe, Fax). */
    public function recordDelivery(Request $request, ConstructionNotice $notice): RedirectResponse {
        Gate::authorize('create', Project::class);

        $data = $request->validate([
            'method' => ['required', 'string', 'in:' . implode(',', ConstructionNoticeService::DELIVERY_METHODS)],
            'delivered_at' => ['required', 'date'],
            'recipient' => ['required', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $this->notices->recordManualDelivery(
            $notice,
            [
                'method' => (string) $data['method'],
                'delivered_at' => $data['delivered_at'],
                'recipient' => (string) $data['recipient'],
                'reference' => $data['reference'] ?? null,
            ],
            app(ConstructionNoticePdfRenderer::class)->render($notice),
            $request->user(),
        );

        return back()->with('status', __('construction.delivery_recorded'));
    }

    public function acknowledge(Request $request, ConstructionNotice $notice): RedirectResponse {
        Gate::authorize('create', Project::class);
        $data = $request->validate(['acknowledged_note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->notices->acknowledge($notice, $data['acknowledged_note'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('construction.acknowledged'));
    }

    public function destroy(ConstructionNotice $notice): RedirectResponse {
        Gate::authorize('create', Project::class);
        $this->notices->delete($notice);

        return redirect()->route('construction-notices.index')->with('status', __('construction.deleted'));
    }

    private function kindFromRequest(Request $request): RenderDocumentKind {
        $kind = RenderDocumentKind::tryFrom((string) $request->input('kind', $request->query('kind', '')));
        abort_unless($kind !== null && in_array($kind, ConstructionNotice::KINDS, true), 404);

        return $kind;
    }
}
