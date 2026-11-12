<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomRequirementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Facility;

use App\Enums\Facility\RoomRequirementKind;
use App\Http\Controllers\Controller;
use App\Models\Facility\{Room, RoomRequirement};
use App\Models\Platform\User;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Raumbezogene fachliche Anforderungen je Gewerk (Feature 027).
 *
 * Verwaltung läuft über die bestehende Raum-/Stammdaten-Permission
 * (RoomPolicy::update); keine eigene Permission. Anforderungen sind 1:n am Raum
 * und ergänzen das Reinigungsprofil um Hygienestufe, Sonderreinigung,
 * Zugangsbeschränkung, IT-Inventar, technische Prüfung und Betreiberpflicht.
 */
class RoomRequirementController extends Controller {
    public function store(Request $request, Room $room): RedirectResponse {
        Gate::authorize('update', $room);

        $data = $this->validateRequirement($request);
        $user = $request->user();

        $room->requirements()->create($data + [
            'organization_id' => $room->organization_id,
            'created_by' => $user instanceof User ? $user->id : null,
            'updated_by' => $user instanceof User ? $user->id : null,
        ]);

        return back()->with('success', __('Raumanforderung hinzugefügt.'));
    }

    public function edit(Room $room, RoomRequirement $requirement): View {
        Gate::authorize('update', $room);
        $this->ensureBelongsToRoom($room, $requirement);

        return view('rooms._requirement_dialog', [
            'room' => $room,
            'requirement' => $requirement,
            'requirementKinds' => RoomRequirementKind::options(),
        ]);
    }

    public function update(Request $request, Room $room, RoomRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $room);
        $this->ensureBelongsToRoom($room, $requirement);

        $data = $this->validateRequirement($request);
        $user = $request->user();
        $requirement->update($data + [
            'updated_by' => $user instanceof User ? $user->id : null,
        ]);

        // Zurück in den Raumdialog, damit die geänderte Anforderung dort sichtbar ist.
        return redirect()->toList('rooms.index')
            ->with('success', __('Raumanforderung aktualisiert.'))
            ->with('open_dialog', route('rooms.edit', $room));
    }

    public function destroy(Request $request, Room $room, RoomRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $room);
        $this->ensureBelongsToRoom($room, $requirement);

        $requirement->delete();

        return back()->with('success', __('Raumanforderung entfernt.'));
    }

    private function ensureBelongsToRoom(Room $room, RoomRequirement $requirement): void {
        if ($requirement->room_id !== $room->id) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function validateRequirement(Request $request): array {
        $data = $request->validate([
            'kind' => ['required', 'string', new Enum(RoomRequirementKind::class)],
            'level' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
