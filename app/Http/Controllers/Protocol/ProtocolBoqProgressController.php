<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolBoqProgressController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Protocol;

use App\Http\Controllers\Controller;
use App\Models\Protocol\Protocol;
use App\Services\Protocol\Contracts\BoqProgressReporter;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * „Fortschritt zur LV-Position melden“ am Protokoll (MVP-1582). Welche
 * Positionen es gibt und ob jemand melden darf, sagt das LV-Modul.
 */
class ProtocolBoqProgressController extends Controller {
    public function __construct(private readonly BoqProgressReporter $reporter) {}

    public function create(Protocol $protocol): View {
        Gate::authorize('view', $protocol);
        $positions = $this->reporter->positionsFor($protocol, $this->authUser());
        abort_if($positions === [], 404);

        return view('protocols._boq_progress_dialog', ['protocol' => $protocol, 'positions' => $positions]);
    }

    public function store(Request $request, Protocol $protocol): RedirectResponse {
        Gate::authorize('view', $protocol);

        $data = $request->validate([
            'position' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->reporter->report($protocol, (string) $data['position'], (string) $data['quantity'], $data['note'] ?? null, $this->authUser());

        return redirect()->route('protocols.show', $protocol)->with('success', __('gaeb.progress.flash.recorded'));
    }
}
