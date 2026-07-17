<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveTeamRequest;
use App\Models\{Task, Team, User};
use App\Services\UI\DateRangeContext;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Verwaltung operativer Arbeits-Teams: Mitglieder, Teamleiter und (über die
 * Projektzuordnung in {@see ProjectController}) die zugewiesenen Aufträge.
 */
class TeamController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Team::class);

        $search = $request->string('q')->toString();

        $teams = Team::query()
            ->withCount('members')
            ->with('lead:id,name')
            ->when($search !== '', fn($q) => $q->search($search))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('teams.index', compact('teams', 'search'));
    }

    public function create(): View {
        Gate::authorize('create', Team::class);

        return view('teams._form_dialog', [
            'team' => new Team,
            'isEdit' => false,
            'assignedMemberIds' => [],
            'orgUsers' => $this->orgUsers(),
        ]);
    }

    public function store(SaveTeamRequest $request): RedirectResponse {
        Gate::authorize('create', Team::class);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validated();

        $team = Team::create([
            'organization_id' => $auth->organization_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'lead_user_id' => $this->validLeadId($data['lead_user_id'] ?? null, (int) $auth->organization_id),
        ]);

        $this->syncMembers($team, $data['member_ids'] ?? []);

        return redirect()->route('teams.show', $team)
            ->with('success', __('Team wurde angelegt.'));
    }

    public function show(Team $team): View {
        Gate::authorize('view', $team);

        $team->load([
            'lead:id,name',
            'members' => fn($q) => $q->withoutGlobalScopes()->orderBy('name'),
            'projects' => fn($q) => $q->orderBy('name'),
        ]);

        $memberIds = $team->members->pluck('id')->all();
        $addableUsers = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $team->organization_id)
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('teams.show', compact('team', 'addableUsers'));
    }

    public function edit(Team $team): View {
        Gate::authorize('update', $team);

        return view('teams._form_dialog', [
            'team' => $team,
            'isEdit' => true,
            'assignedMemberIds' => $team->members()->pluck('users.id')->all(),
            'orgUsers' => $this->orgUsers(),
        ]);
    }

    public function update(SaveTeamRequest $request, Team $team): RedirectResponse {
        Gate::authorize('update', $team);

        $data = $request->validated();

        $team->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'lead_user_id' => $this->validLeadId($data['lead_user_id'] ?? null, (int) $team->organization_id),
        ]);

        if (array_key_exists('member_ids', $data)) {
            $this->syncMembers($team, $data['member_ids'] ?? []);
        }

        return redirect()->route('teams.show', $team)
            ->with('success', __('Team wurde aktualisiert.'));
    }

    public function destroy(Team $team): RedirectResponse {
        Gate::authorize('delete', $team);

        $team->delete();

        return redirect()->route('teams.index')
            ->with('success', __('Team wurde gelöscht.'));
    }

    /**
     * Team-Auslastung: pro Mitglied dessen Aufgaben über alle Aufträge im
     * gewählten Zeitraum (wer macht was wann), inkl. Deadline-Hervorhebung.
     */
    public function workload(Team $team, DateRangeContext $range): View {
        Gate::authorize('view', $team);

        $r = $range->current();
        $from = $r['from']->toDateString();
        $to = $r['to']->toDateString();

        $team->load(['members' => fn($q) => $q->withoutGlobalScopes()->orderBy('name')]);
        $memberIds = $team->members->pluck('id')->all();

        // Aufgaben der Team-Mitglieder (Mehrfach-Zuweisung über Pivot), die den
        // Zeitraum überlappen (Start oder Deadline im Fenster, oder laufend).
        $tasks = Task::query()
            ->whereHas('assignees', fn($q) => $q->whereIn('users.id', $memberIds))
            ->with(['project:id,name,slug,customer_id', 'assignees:id,name'])
            ->where(function ($q) use ($from, $to): void {
                $q->whereBetween('due_date', [$from, $to])
                    ->orWhereBetween('start_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from): void {
                        $q2->whereNotNull('start_date')->where('start_date', '<=', $from);
                    });
            })
            ->orderBy('due_date')
            ->get();

        // Pro Mitglied dessen Aufgaben (eine Aufgabe kann bei mehreren auftauchen).
        $byMember = [];
        foreach ($tasks as $task) {
            foreach ($task->assignees as $assignee) {
                if (in_array($assignee->id, $memberIds, true)) {
                    $byMember[$assignee->id][] = $task;
                }
            }
        }
        $byMember = collect($byMember)->map(fn(array $rows) => collect($rows));

        return view('teams.workload', [
            'team' => $team,
            'byMember' => $byMember,
            'range' => $r,
        ]);
    }

    public function attachMemberForm(Team $team): View {
        Gate::authorize('manageMembers', $team);

        $memberIds = $team->members()->pluck('users.id')->all();
        $addableUsers = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $team->organization_id)
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('teams._attach_member_dialog', compact('team', 'addableUsers'));
    }

    public function attachMember(Request $request, Team $team): RedirectResponse {
        Gate::authorize('manageMembers', $team);

        $request->validate([
            'user_id' => ['required', 'string'],
        ]);

        // Sqid bevorzugt; numerische ID als Fallback (z. B. interne Aufrufe/Tests).
        $raw = (string) $request->input('user_id');
        $userId = Sqid::decode(User::class, $raw) ?? (is_numeric($raw) ? (int) $raw : null);
        abort_if($userId === null, 422, (string) __('Ungültige Auswahl.'));

        /** @var User|null $user */
        $user = User::query()->find($userId);
        abort_unless($user instanceof User && $user->organization_id === $team->organization_id, 422);

        $team->members()->syncWithoutDetaching([
            $user->id => ['joined_at' => Carbon::now()],
        ]);

        return back()->with('success', __('Mitglied wurde hinzugefügt.'));
    }

    public function detachMember(Team $team, User $user): RedirectResponse {
        Gate::authorize('manageMembers', $team);
        abort_unless($user->organization_id === $team->organization_id, 403);

        $team->members()->detach($user->id);

        // War die Person Teamleiter, Leitung zurücksetzen.
        if ((int) $team->lead_user_id === (int) $user->id) {
            $team->update(['lead_user_id' => null]);
        }

        return back()->with('success', __('Mitglied wurde entfernt.'));
    }

    /**
     * Alle Benutzer der aktuellen Organisation (für Leiter-/Mitglieder-Auswahl).
     *
     * @return Collection<int, User>
     */
    private function orgUsers(): Collection {
        /** @var User $auth */
        $auth = Auth::user();

        return User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $auth->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Stellt sicher, dass der gewählte Teamleiter zur Organisation gehört. */
    private function validLeadId(?int $leadId, int $organizationId): ?int {
        if ($leadId === null) {
            return null;
        }
        $exists = User::query()
            ->withoutGlobalScopes()
            ->whereKey($leadId)
            ->where('organization_id', $organizationId)
            ->exists();

        return $exists ? $leadId : null;
    }

    /**
     * Synchronisiert die Mitglieder (nur Benutzer der eigenen Organisation).
     * Der Teamleiter wird stets als Mitglied geführt und im Pivot markiert.
     *
     * @param  list<int>  $memberIds
     */
    private function syncMembers(Team $team, array $memberIds): void {
        if ($team->lead_user_id !== null) {
            $memberIds[] = (int) $team->lead_user_id;
        }

        $validIds = User::query()
            ->withoutGlobalScopes()
            ->whereIn('id', array_unique($memberIds))
            ->where('organization_id', $team->organization_id)
            ->pluck('id')
            ->all();

        $pivot = [];
        foreach ($validIds as $id) {
            $pivot[$id] = [
                'is_lead' => (int) $id === (int) $team->lead_user_id,
                'joined_at' => Carbon::now(),
            ];
        }

        $team->members()->sync($pivot);
    }
}
