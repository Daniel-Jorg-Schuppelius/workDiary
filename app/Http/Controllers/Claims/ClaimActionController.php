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
use App\Models\Diary\DiaryEntry;
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};

/**
 * Maßnahmensteuerung (Feature 072, MVP-251): Nacharbeit/Reparatur/Ersatz/
 * Serviceeinsatz mit Verantwortlichem, Frist und Folgeauftrag; Statuspflege inklusive.
 */
class ClaimActionController extends Controller {
    public function store(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('update', $claim);

        if ($request->filled('assigned_user_id')) {
            $request->merge(['assigned_user_id' => Sqid::decodeOrNumeric(\App\Models\Platform\User::class, $request->input('assigned_user_id'))]);
        }
        $request->merge(['follow_up_order_id' => Sqid::decodeOrNumeric(DiaryEntry::class, $request->input('follow_up_order_id'))]);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ClaimActionKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:4000'],
            'assigned_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'due_at' => ['nullable', 'date'],
            'follow_up_order_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
        ]);
        $followUp = $this->followUpOrder($claim, $data['follow_up_order_id'] ?? null);
        unset($data['follow_up_order_id']);

        $claim->actions()->create(array_merge($data, $this->followUpColumns($followUp), [
            'organization_id' => $claim->organization_id,
            'status' => ClaimActionStatus::Planned->value,
            'created_by' => ($request->user() ?? abort(401))->id,
        ]));

        return back()->with('status', __('Maßnahme angelegt.'));
    }

    public function update(Request $request, ClaimAction $action): RedirectResponse {
        $claim = $action->claimCase ?? abort(404);
        Gate::authorize('update', $claim);

        $linksOrder = $request->exists('follow_up_order_id');
        if ($linksOrder) {
            $request->merge(['follow_up_order_id' => Sqid::decodeOrNumeric(DiaryEntry::class, $request->input('follow_up_order_id'))]);
        }
        $data = $request->validate([
            'status' => ['required', Rule::enum(ClaimActionStatus::class)],
            'note' => ['nullable', 'string', 'max:4000'],
            'follow_up_order_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
        ]);

        $status = ClaimActionStatus::from($data['status']);
        $action->forceFill([
            'status' => $status->value,
            'note' => $data['note'] ?? $action->note,
            'done_at' => $status === ClaimActionStatus::Done ? now() : null,
            // Ein Aufruf ohne das Feld lässt den Folgeauftrag stehen.
            ...($linksOrder ? $this->followUpColumns($this->followUpOrder($claim, $data['follow_up_order_id'] ?? null)) : []),
        ])->save();

        return back()->with('status', __('Maßnahme aktualisiert.'));
    }

    /**
     * Folgeauftrag der Maßnahme (MVP-1623): ein Auftrag, den der Bearbeiter
     * öffnen darf, beim Kunden der Akte — darüber läuft der Serviceeinsatz in
     * Disposition und Tourplanung.
     */
    private function followUpOrder(ClaimCase $claim, ?int $orderId): ?DiaryEntry {
        if ($orderId === null) {
            return null;
        }
        $order = DiaryEntry::query()->findOrFail($orderId);
        if (! Gate::allows('view', $order) || ($claim->customer_id !== null && (int) $order->customer_id !== (int) $claim->customer_id)) {
            throw ValidationException::withMessages(['follow_up_order_id' => __('validation.exists', ['attribute' => __('validation.attributes.follow_up_order_id')])]);
        }

        return $order;
    }

    /** @return array{follow_up_type: ?string, follow_up_id: ?int} */
    private function followUpColumns(?DiaryEntry $order): array {
        return ['follow_up_type' => $order?->getMorphClass(), 'follow_up_id' => $order?->id];
    }
}
