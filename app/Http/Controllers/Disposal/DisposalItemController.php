<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalItemController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Disposal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Disposal\{SaveDataMediaTreatmentRequest, SaveDisposalItemRequest};
use App\Models\Disposal\{DataMediaTreatment, DisposalItem, DisposalJob};
use App\Services\Disposal\DisposalJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Gerätepositionen + Datenträger-Behandlungen einer Entsorgungsakte
 * (Feature 100). Kind-Objekte werden gegen die Akte autorisiert; die
 * Mandantengrenze zieht der org-gescopte Job-Lookup (Cross-Org → 404).
 */
class DisposalItemController extends Controller {
    public function __construct(private readonly DisposalJobService $service) {}

    public function store(SaveDisposalItemRequest $request, DisposalJob $disposalJob): RedirectResponse {
        Gate::authorize('update', $disposalJob);

        $actor = $request->user() ?? abort(401);

        try {
            $this->service->addItem($disposalJob, $actor, $request->validated());
        } catch (Throwable $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        return redirect()->route('disposal.show', $disposalJob)
            ->with('status', __('Geräteposition erfasst.'));
    }

    public function update(SaveDisposalItemRequest $request, DisposalItem $disposalItem): RedirectResponse {
        $job = $this->jobFor($disposalItem);
        Gate::authorize('update', $job);

        $actor = $request->user() ?? abort(401);

        try {
            $this->service->updateItem($disposalItem, $actor, $request->validated());
        } catch (Throwable $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        return redirect()->route('disposal.show', $job)
            ->with('status', __('Geräteposition aktualisiert.'));
    }

    public function destroy(DisposalItem $disposalItem): RedirectResponse {
        $job = $this->jobFor($disposalItem);
        Gate::authorize('update', $job);

        $actor = request()->user() ?? abort(401);

        try {
            $this->service->removeItem($disposalItem, $actor);
        } catch (Throwable $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        return redirect()->route('disposal.show', $job)
            ->with('status', __('Geräteposition entfernt.'));
    }

    public function storeTreatment(SaveDataMediaTreatmentRequest $request, DisposalItem $disposalItem): RedirectResponse {
        $job = $this->jobFor($disposalItem);
        Gate::authorize('update', $job);

        $actor = $request->user() ?? abort(401);

        try {
            $this->service->addTreatment($disposalItem, $actor, $request->validated());
        } catch (Throwable $exception) {
            return back()->withErrors(['treatments' => $exception->getMessage()]);
        }

        return redirect()->route('disposal.show', $job)
            ->with('status', __('Datenträger-Behandlung dokumentiert.'));
    }

    public function destroyTreatment(DataMediaTreatment $dataMediaTreatment): RedirectResponse {
        $item = $dataMediaTreatment->item()->firstOrFail();
        $job = $this->jobFor($item);
        Gate::authorize('update', $job);

        $actor = request()->user() ?? abort(401);

        try {
            $this->service->removeTreatment($dataMediaTreatment, $actor);
        } catch (Throwable $exception) {
            return back()->withErrors(['treatments' => $exception->getMessage()]);
        }

        return redirect()->route('disposal.show', $job)
            ->with('status', __('Datenträger-Behandlung entfernt.'));
    }

    /** Org-gescopter Eltern-Lookup: fremde Akten lösen 404 aus. */
    private function jobFor(DisposalItem $item): DisposalJob {
        return $item->job()->firstOrFail();
    }
}
