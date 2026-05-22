<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\Event\RoomBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller {
    public function index(Request $request, RoomBookingService $bookings): View {
        Gate::authorize('viewAny', Room::class);

        $view = $request->query('view', 'list');
        $day = $request->query('day') ? Carbon::parse((string) $request->query('day')) : Carbon::today();

        $rooms = Room::query()->orderBy('name')->paginate(50)->withQueryString();
        $grid = $view === 'grid' ? $bookings->gridForDay($day) : [];
        $gridRooms = $view === 'grid' ? Room::query()->active()->orderBy('name')->get() : collect();

        return view('rooms.index', [
            'rooms' => $rooms,
            'view' => $view,
            'day' => $day,
            'grid' => $grid,
            'gridRooms' => $gridRooms,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Room::class);

        return view('rooms._form_dialog', ['room' => null, 'isEdit' => false]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Room::class);
        Room::create($this->validateRoom($request));

        return redirect()->route('rooms.index')->with('success', __('Raum angelegt.'));
    }

    public function edit(Room $room): View {
        Gate::authorize('update', $room);

        return view('rooms._form_dialog', ['room' => $room, 'isEdit' => true]);
    }

    public function update(Request $request, Room $room): RedirectResponse {
        Gate::authorize('update', $room);
        $room->update($this->validateRoom($request));

        return redirect()->route('rooms.index')->with('success', __('Raum aktualisiert.'));
    }

    public function destroy(Room $room): RedirectResponse {
        Gate::authorize('delete', $room);
        $room->delete();

        return redirect()->route('rooms.index')->with('success', __('Raum gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateRoom(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:32'],
            'building' => ['nullable', 'string', 'max:120'],
            'floor' => ['nullable', 'string', 'max:32'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:40'],
            'color' => ['nullable', 'string', 'max:9'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_active'] ??= false;

        return $data;
    }
}
