<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DpiaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\{Dpia, ProcessingActivity};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Datenschutz-Folgenabschaetzung (Art. 35) je Verarbeitungstaetigkeit. Eine DSFA
 * pro Taetigkeit; Upsert. Bei outcome != open wird sie als bewertet markiert.
 */
class DpiaController extends Controller {
    public function store(Request $request, ProcessingActivity $activity): RedirectResponse {
        Gate::authorize('create', Dpia::class);
        // Taetigkeit muss zur eigenen Org gehoeren (Scope greift, aber explizit).
        abort_unless((int) $activity->organization_id === (int) ($request->user()?->organization_id), 403);

        $data = $request->validate([
            'necessity' => ['nullable', 'string', 'max:20000'],
            'risks' => ['nullable', 'string', 'max:20000'],
            'mitigations' => ['nullable', 'string', 'max:20000'],
            'residual_risk' => ['nullable', 'in:low,medium,high'],
            'outcome' => ['required', 'in:open,proceed,consult,abort'],
        ]);

        $assessed = $data['outcome'] !== 'open';
        Dpia::query()->updateOrCreate(
            ['organization_id' => $activity->organization_id, 'activity_id' => $activity->id],
            [
                ...$data,
                'assessed_by' => $assessed ? $request->user()?->id : null,
                'assessed_at' => $assessed ? now() : null,
            ],
        );

        // DSFA-Bedarf am VVT markieren.
        $activity->forceFill(['dsfa_required' => true])->save();

        return redirect()->route('dataprotection.activities.show', $activity)
            ->with('status', __('DSFA gespeichert.'));
    }
}
