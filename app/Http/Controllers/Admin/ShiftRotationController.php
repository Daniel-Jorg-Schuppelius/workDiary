<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftRotationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Organization, ShiftRotation, ShiftRotationAssignment, ShiftType, User};
use App\Services\Schedule\ShiftRotationRoller;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Rollplan-Pflege (MVP-522) — admin-gebunden wie Terminals/Zeitdimensionen.
 * Rhythmen (Wochenraster × Schichttyp), Zuweisungen (Mitarbeiter + Anker-
 * Montag) und manuelle Fortschreibung.
 */
class ShiftRotationController extends Controller {
    public function index(): View {
        $this->authorizeAdmin();

        return view('admin.shift-rotations.index', [
            'rotations' => ShiftRotation::query()
                ->with(['entries', 'assignments.user:id,name', 'assignments.rotation:id,name'])
                ->orderBy('name')
                ->get(),
            'shiftTypes' => ShiftType::query()->where('is_active', true)->orderBy('name')->get(),
            'members' => User::query()
                ->where('organization_id', $this->organizationId())
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function create(): View {
        $this->authorizeAdmin();

        return view('admin.shift-rotations._form_dialog', ['isDialog' => true]);
    }

    public function store(Request $request): RedirectResponse {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'weeks_count' => ['required', 'integer', 'min:1', 'max:26'],
        ]);

        $rotation = ShiftRotation::query()->create([
            'organization_id' => $this->organizationId(),
            'name' => $data['name'],
            'weeks_count' => (int) $data['weeks_count'],
            'is_active' => true,
        ]);
        $rotation->audit('shiftRotation.created', ['name' => $rotation->name, 'weeks' => $rotation->weeks_count]);

        return redirect()->route('admin.shift-rotations.index')->with('status', __('Rollplan angelegt.'));
    }

    /** Wochenraster speichern: entries[week][weekday] = ShiftType-Sqid oder leer. */
    public function updateEntries(Request $request, ShiftRotation $rotation): RedirectResponse {
        $this->authorizeAdmin();

        $data = $request->validate([
            'entries' => ['sometimes', 'array'],
            'entries.*' => ['array'],
            'entries.*.*' => ['nullable', 'string'],
        ]);

        $rows = [];
        foreach ((array) ($data['entries'] ?? []) as $week => $days) {
            $week = (int) $week;
            if ($week < 0 || $week >= $rotation->weeks_count) {
                continue;
            }
            foreach ((array) $days as $weekday => $sqid) {
                $weekday = (int) $weekday;
                if ($weekday < 1 || $weekday > 7 || $sqid === null || $sqid === '') {
                    continue;
                }
                $shiftTypeId = Sqid::decodeOrNumeric(ShiftType::class, (string) $sqid);
                // Org-Grenze: nur Schichttypen der eigenen Organisation (globaler Scope).
                if (! ShiftType::query()->whereKey($shiftTypeId)->exists()) {
                    continue;
                }
                $rows[] = ['week_index' => $week, 'iso_weekday' => $weekday, 'shift_type_id' => (int) $shiftTypeId];
            }
        }

        $rotation->entries()->delete();
        foreach ($rows as $row) {
            $rotation->entries()->create($row);
        }
        $rotation->audit('shiftRotation.entriesUpdated', ['slots' => count($rows)]);

        return back()->with('status', __('Wochenraster gespeichert.'));
    }

    public function toggle(ShiftRotation $rotation): RedirectResponse {
        $this->authorizeAdmin();

        $rotation->update(['is_active' => ! $rotation->is_active]);
        $rotation->audit($rotation->is_active ? 'shiftRotation.activated' : 'shiftRotation.deactivated', []);

        return back()->with('status', __('Rollplan aktualisiert.'));
    }

    public function storeAssignment(Request $request, ShiftRotation $rotation): RedirectResponse {
        $this->authorizeAdmin();

        if ($request->filled('user_id')) {
            $request->merge(['user_id' => Sqid::decodeOrNumeric(User::class, (string) $request->input('user_id'))]);
        }
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'anchor_date' => ['required', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $user = User::query()
            ->where('organization_id', $this->organizationId())
            ->findOrFail((int) $data['user_id']);

        $assignment = $rotation->assignments()->create([
            'organization_id' => $this->organizationId(),
            'user_id' => $user->getKey(),
            'anchor_date' => $data['anchor_date'],
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
        ]);
        $assignment->audit('shiftRotation.assigned', ['user_id' => (int) $user->getKey(), 'anchor' => $data['anchor_date']]);

        return back()->with('status', __('Zuweisung gespeichert.'));
    }

    public function destroyAssignment(ShiftRotationAssignment $assignment): RedirectResponse {
        $this->authorizeAdmin();

        $assignment->audit('shiftRotation.unassigned', ['user_id' => (int) $assignment->user_id]);
        $assignment->delete();

        return back()->with('status', __('Zuweisung entfernt.'));
    }

    /** Fortschreibung sofort ausführen (zusätzlich zum täglichen Scheduler-Lauf). */
    public function roll(Request $request, ShiftRotationRoller $roller): RedirectResponse {
        $this->authorizeAdmin();

        $validated = $request->validate(['weeks' => ['nullable', 'integer', 'min:1', 'max:26']]);
        $weeks = (int) ($validated['weeks'] ?? 4);
        $org = app('currentOrganization');
        if (! $org instanceof Organization) {
            abort(404);
        }

        $stats = $roller->rollForward($org, CarbonImmutable::now(), $weeks > 0 ? $weeks : 4);

        return back()->with('status', __(':created Dienste erzeugt, :skipped Slots übersprungen.', [
            'created' => $stats['created'],
            'skipped' => $stats['skipped'],
        ]));
    }

    private function authorizeAdmin(): void {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user !== null && $user->isAdmin(), 403);
    }

    private function organizationId(): int {
        /** @var User $user */
        $user = Auth::user();

        return (int) $user->organization_id;
    }
}
