<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJobController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Disposal;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Disposal\DisposalJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disposal\{SaveDisposalJobRequest, SignDisposalJobRequest};
use App\Models\Asset\Asset;
use App\Models\Contacts\ExternalContact;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Disposal\DisposalJob;
use App\Models\Facility\Site;
use App\Models\Platform\User;
use App\Services\Classification\ClassificationResolver;
use App\Services\Disposal\{DisposalJobService, DisposalRecordPdfRenderer};
use App\Support\{ErrorText, Sqid};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Entsorgungsakten (Feature 100, MVP-474/475): Liste mit Status-KPIs,
 * Akte mit Geräteliste, Behandlungen, Übergaben, Unterschrift und
 * Kundennachweis. Anlegen/Bearbeiten als Dialog; Statusübergänge als
 * eigene POST-Aktionen mit deutschen Verb-Segmenten.
 */
class DisposalJobController extends Controller {
    public function __construct(
        private readonly DisposalJobService $service,
        private readonly DisposalRecordPdfRenderer $recordRenderer,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', DisposalJob::class);

        $statusFilter = DisposalJobStatus::tryFrom($request->string('status')->toString())?->value;
        // Kachelfilter (ux-pattern-katalog §4): offen bzw. im laufenden Jahr abgeschlossen; die Kachelwerte bleiben stehen.
        $show = $request->string('show')->toString();
        if (! in_array($show, ['open', 'completed_year'], true)) {
            $show = '';
        }
        $customerId = $request->filled('customer_id')
            ? Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id'))
            : null;

        return view('disposal.index', [
            'jobs' => DisposalJob::query()
                ->with(['customer', 'site', 'responsible'])
                ->withCount('items')
                ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
                ->when($show === 'open', fn($q) => $q->open())
                ->when($show === 'completed_year', fn($q) => $q->where('status', DisposalJobStatus::Completed->value)->whereBetween('completed_at', [now()->startOfYear(), now()->endOfYear()]))
                ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
                ->when($request->boolean('hazardous'), fn($q) => $q->whereHas('items', fn($i) => $i->where('is_hazardous', true)))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'show' => $show,
            'openCount' => DisposalJob::query()->open()->count(),
            'hazardousOpenCount' => DisposalJob::query()->open()
                ->whereHas('items', fn($q) => $q->where('is_hazardous', true))->count(),
            'completedCount' => DisposalJob::query()
                ->where('status', DisposalJobStatus::Completed->value)
                ->whereBetween('completed_at', [now()->startOfYear(), now()->endOfYear()])
                ->count(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, DisposalJob $disposalJob): View {
        Gate::authorize('view', $disposalJob);

        $disposalJob->load([
            'customer', 'site', 'diaryEntry', 'responsible', 'creator',
            'items.treatments.performer', 'items.asset', 'items.attachments',
            'handovers.disposer', 'handovers.document.currentVersion',
            'recordDocument.currentVersion', 'journal.actor', 'signatureAttachment',
        ]);

        return view('disposal.show', [
            'job' => $disposalJob,
            'completionBlockers' => $disposalJob->status->isOpen() ? $this->service->completionBlockers($disposalJob) : [],
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'disposers' => ExternalContact::query()->orderBy('name')->get(['id', 'name']),
            'wasteCodes' => app(ClassificationResolver::class)->list(
                (int) $disposalJob->organization_id,
                ClassificationDomain::WasteCode,
            ),
            // Nur Assets in Betrieb lassen sich entsorgen (MVP-1578); bereits verknüpfte bleiben über die Position sichtbar.
            'assets' => Asset::query()->inOperation()->orderBy('name')->get(['id', 'asset_no', 'name', 'manufacturer', 'model', 'serial_no']),
            'prefillAsset' => $this->assetFromRequest($request),
            'pendingAssets' => $disposalJob->status === DisposalJobStatus::Completed
                ? $disposalJob->items->pluck('asset')->filter(fn (?Asset $asset): bool => $asset !== null && $asset->isInOperation())->values()
                : collect(),
        ]);
    }

    /** Asset aus dem Einstieg an der Asset-Akte: nur in Betrieb und für den Nutzer sichtbar. */
    private function assetFromRequest(Request $request): ?Asset {
        $id = Sqid::decode(Asset::class, $request->string('asset')->toString());
        $asset = $id !== null ? Asset::query()->inOperation()->find($id) : null;

        return $asset !== null && Gate::allows('view', $asset) ? $asset : null;
    }

    /** Anlege-Dialog (Formulare in Dialogen). */
    public function create(Request $request): View {
        Gate::authorize('create', DisposalJob::class);

        return view('disposal._form_dialog', [
            'job' => null,
            'prefillAsset' => $this->assetFromRequest($request),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'customer_id']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'diaryEntries' => DiaryEntry::query()->open()->orderByDesc('id')->limit(100)->get(['id', 'title', 'customer_id']),
        ]);
    }

    /** Bearbeiten-Dialog für die Kopfdaten (nur solange die Akte änderbar ist). */
    public function edit(DisposalJob $disposalJob): View {
        Gate::authorize('update', $disposalJob);

        return view('disposal._form_dialog', [
            'job' => $disposalJob,
            'prefillAsset' => null,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'customer_id']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'diaryEntries' => DiaryEntry::query()->open()->orderByDesc('id')->limit(100)->get(['id', 'title', 'customer_id']),
        ]);
    }

    public function store(SaveDisposalJobRequest $request): RedirectResponse {
        Gate::authorize('create', DisposalJob::class);

        $actor = $request->user() ?? abort(401);
        $organization = $actor->organization ?? abort(403);

        $job = $this->service->open($organization, $actor, $request->validated());

        // Einstieg an der Asset-Akte: die Akte öffnet mit dem Asset als vorbereiteter Position.
        $asset = $this->assetFromRequest($request);

        return redirect()->route('disposal.show', array_filter(['disposalJob' => $job, 'asset' => $asset?->sqid]))
            ->with('status', __('Entsorgungsakte :number angelegt.', ['number' => $job->number]));
    }

    public function update(SaveDisposalJobRequest $request, DisposalJob $disposalJob): RedirectResponse {
        Gate::authorize('update', $disposalJob);

        $actor = $request->user() ?? abort(401);

        try {
            $this->service->update($disposalJob, $actor, $request->validated());
        } catch (Throwable $exception) {
            return back()->withErrors(['status' => ErrorText::for($exception)]);
        }

        return redirect()->route('disposal.show', $disposalJob)
            ->with('status', __('Entsorgungsakte aktualisiert.'));
    }

    public function collect(Request $request, DisposalJob $disposalJob): RedirectResponse {
        return $this->doTransition($request, $disposalJob, DisposalJobStatus::Collected, __('Abholung erfasst.'));
    }

    public function startTreatment(Request $request, DisposalJob $disposalJob): RedirectResponse {
        return $this->doTransition($request, $disposalJob, DisposalJobStatus::InTreatment, __('Behandlung gestartet.'));
    }

    public function markHandedOver(Request $request, DisposalJob $disposalJob): RedirectResponse {
        return $this->doTransition($request, $disposalJob, DisposalJobStatus::HandedOver, __('Übergabe an den Entsorger erfasst.'));
    }

    /** Übernahme-Unterschrift des Kunden (Signature-Pad). */
    public function sign(SignDisposalJobRequest $request, DisposalJob $disposalJob): RedirectResponse {
        Gate::authorize('update', $disposalJob);

        $actor = $request->user() ?? abort(401);
        $data = $request->validated();

        try {
            $this->service->sign($disposalJob, $actor, (string) $data['signer_name'], (string) $data['signature']);
        } catch (Throwable $exception) {
            return back()->withErrors(['signature' => ErrorText::for($exception)]);
        }

        return redirect()->route('disposal.show', $disposalJob)
            ->with('status', __('Übernahme unterschrieben.'));
    }

    /** Bewachter Abschluss: Gates prüfen, Assets ausmustern, Kundennachweis erzeugen. */
    public function complete(Request $request, DisposalJob $disposalJob): RedirectResponse {
        Gate::authorize('complete', $disposalJob);

        $actor = $request->user() ?? abort(401);

        try {
            $this->service->complete($disposalJob, $actor);
        } catch (Throwable $exception) {
            return back()->withErrors(['status' => ErrorText::for($exception)]);
        }

        $redirect = redirect()->route('disposal.show', $disposalJob)
            ->with('status', __('Entsorgungsakte abgeschlossen — der Kundennachweis wurde erzeugt und freigegeben.'));

        // Noch ausgegebene oder gesperrte Assets mustert der Abschluss nicht aus — das darf nicht still bleiben.
        $pending = $disposalJob->items()->with('asset')->get()->pluck('asset')
            ->filter(fn (?Asset $asset): bool => $asset !== null && $asset->isInOperation());

        return $pending->isEmpty()
            ? $redirect
            : $redirect->with('warning', __('disposal.asset.pending_decommission', ['assets' => $pending->pluck('name')->implode(', ')]));
    }

    public function cancel(Request $request, DisposalJob $disposalJob): RedirectResponse {
        Gate::authorize('complete', $disposalJob);

        $actor = $request->user() ?? abort(401);
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        try {
            $this->service->cancel($disposalJob, $actor, $reason);
        } catch (Throwable $exception) {
            return back()->withErrors(['status' => ErrorText::for($exception)]);
        }

        return redirect()->route('disposal.show', $disposalJob)
            ->with('status', __('Entsorgungsakte storniert.'));
    }

    /** Nachweis-PDF (Vorschau auf dem aktuellen Stand; der archivierte Nachweis liegt im DMS). */
    public function pdf(DisposalJob $disposalJob): Response {
        Gate::authorize('view', $disposalJob);

        return response($this->recordRenderer->render($disposalJob), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->recordRenderer->filename($disposalJob) . '.pdf"',
        ]);
    }

    private function doTransition(Request $request, DisposalJob $disposalJob, DisposalJobStatus $target, string $message): RedirectResponse {
        Gate::authorize('update', $disposalJob);

        $actor = $request->user() ?? abort(401);

        $note = $request->string('note')->toString();

        try {
            $this->service->transition($disposalJob, $actor, $target, $note !== '' ? $note : null);
        } catch (Throwable $exception) {
            return back()->withErrors(['status' => ErrorText::for($exception)]);
        }

        return redirect()->route('disposal.show', $disposalJob)->with('status', $message);
    }
}
