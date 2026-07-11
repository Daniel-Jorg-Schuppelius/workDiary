<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimActionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Claims;

use App\Enums\Claims\{ClaimActionKind, ClaimActionStatus};
use App\Http\Controllers\Controller;
use App\Models\Claims\{ClaimAction, ClaimCase};
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Maßnahmensteuerung (Feature 072, MVP-251): Nacharbeit/Reparatur/Ersatz/
 * Serviceeinsatz mit Verantwortlichem und Frist; Statuspflege inklusive.
 */
class ClaimActionController extends Controller {
    public function store(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('update', $claim);

        if ($request->filled('assigned_user_id')) {
            $request->merge(['assigned_user_id' => Sqid::decodeOrNumeric(\App\Models\User::class, $request->input('assigned_user_id'))]);
        }
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ClaimActionKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:4000'],
            'assigned_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'due_at' => ['nullable', 'date'],
        ]);

        $claim->actions()->create(array_merge($data, [
            'organization_id' => $claim->organization_id,
            'status' => ClaimActionStatus::Planned->value,
            'created_by' => ($request->user() ?? abort(401))->id,
        ]));

        return back()->with('status', __('Maßnahme angelegt.'));
    }

    public function update(Request $request, ClaimAction $action): RedirectResponse {
        Gate::authorize('update', $action->claimCase);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ClaimActionStatus::class)],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $status = ClaimActionStatus::from($data['status']);
        $action->forceFill([
            'status' => $status->value,
            'note' => $data['note'] ?? $action->note,
            'done_at' => $status === ClaimActionStatus::Done ? now() : null,
        ])->save();

        return back()->with('status', __('Maßnahme aktualisiert.'));
    }
}
