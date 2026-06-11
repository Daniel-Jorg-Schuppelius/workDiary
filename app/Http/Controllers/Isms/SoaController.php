<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Models\Isms\IsmsControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Statement of Applicability (Feature 044, MVP 1): Read-Only-Tabelle aller
 * Controls mit Anwendbarkeit, Begründung, Umsetzungsstatus und verknüpften
 * Risiken. Standard ist der Dialog (entry-modal, Hausstandard); mit
 * `?print=1` bleibt die druckbare Standalone-Ansicht erreichbar
 * (Muster: Fallakte).
 */
class SoaController extends Controller {
    public function __invoke(Request $request): View {
        Gate::authorize('viewAny', IsmsControl::class);

        $controls = IsmsControl::query()
            ->with(['risks' => fn($q) => $q->orderBy('risk_no'), 'owner'])
            ->get()
            ->sortBy('code', SORT_NATURAL)
            ->values();

        $data = [
            'controls' => $controls,
            'generatedAt' => now(),
            'organizationName' => (string) (app()->bound('currentOrganization')
                ? app('currentOrganization')->name
                : ''),
        ];

        return $request->boolean('print')
            ? view('isms.soa', $data)
            : view('isms._soa_dialog', $data);
    }
}
