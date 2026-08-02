<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalHandoverController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Disposal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Disposal\SaveDisposalHandoverRequest;
use App\Models\Disposal\{DisposalHandover, DisposalJob};
use App\Services\Disposal\DisposalJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Entsorger-Übergaben einer Entsorgungsakte (Feature 100): Nachweistyp,
 * Belegnummer, Datum, optionaler DMS-Beleg-Upload und EfbV-Referenz.
 */
class DisposalHandoverController extends Controller {
    public function __construct(private readonly DisposalJobService $service) {}

    public function store(SaveDisposalHandoverRequest $request, DisposalJob $disposalJob): RedirectResponse {
        Gate::authorize('update', $disposalJob);

        $actor = $request->user() ?? abort(401);
        $data = $request->validated();
        $proofFile = $request->file('proof_file');
        unset($data['proof_file']);

        try {
            $this->service->addHandover($disposalJob, $actor, $data, $proofFile);
        } catch (Throwable $exception) {
            return back()->withErrors(['handovers' => $exception->getMessage()]);
        }

        return redirect()->route('disposal.show', $disposalJob)
            ->with('status', __('Entsorger-Übergabe erfasst.'));
    }

    public function destroy(DisposalHandover $disposalHandover): RedirectResponse {
        $job = $disposalHandover->job()->firstOrFail();
        Gate::authorize('update', $job);

        $actor = request()->user() ?? abort(401);

        try {
            $this->service->removeHandover($disposalHandover, $actor);
        } catch (Throwable $exception) {
            return back()->withErrors(['handovers' => $exception->getMessage()]);
        }

        return redirect()->route('disposal.show', $job)
            ->with('status', __('Entsorger-Übergabe entfernt.'));
    }
}
