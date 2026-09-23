<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventHorseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{AssignHorseRequest, RecordHorseUseRequest};
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventDetails, ClubHorse, ClubHorseAssignment, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\ClubHorseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};

/** Reiter–Pferd-Zuordnung und Pferdeeinsatz je Reitstunde (MVP-854); Rechte wie Teilnehmerverwaltung. */
class ClubEventHorseController extends Controller {
    public function __construct(
        private readonly ClubHorseService $horses,
    ) {}

    public function assign(AssignHorseRequest $request, Event $event): RedirectResponse {
        Gate::authorize('manageParticipants', $this->details($event));
        $data = $request->validated();
        /** @var ClubMember $rider */
        $rider = ClubMember::query()->whereKey((int) $data['club_member_id'])->firstOrFail();
        $horse = isset($data['club_horse_id']) ? ClubHorse::query()->whereKey((int) $data['club_horse_id'])->firstOrFail() : null;
        /** @var User $actor */
        $actor = Auth::user();
        $this->horses->assign($event, $rider, $horse, $actor, (bool) ($data['own_horse'] ?? false), (bool) ($data['override'] ?? false), $data['override_note'] ?? null);

        return redirect()->route('club.events.show', $event)->with('success', __('club.horses.flash.assigned', ['name' => $rider->fullName(), 'horse' => $horse !== null ? $horse->name : __('club.horses.label.own_horse')]));
    }

    public function unassign(Event $event, ClubHorseAssignment $assignment): RedirectResponse {
        Gate::authorize('manageParticipants', $this->details($event));
        abort_unless($assignment->event_id === $event->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->horses->unassign($assignment, $actor);

        return redirect()->route('club.events.show', $event)->with('success', __('club.horses.flash.unassigned'));
    }

    public function recordUse(RecordHorseUseRequest $request, Event $event): RedirectResponse {
        Gate::authorize('manageParticipants', $this->details($event));
        $data = $request->validated();
        /** @var ClubHorse $horse */
        $horse = ClubHorse::query()->whereKey((int) $data['club_horse_id'])->firstOrFail();
        $rider = isset($data['club_member_id']) ? ClubMember::query()->whereKey((int) $data['club_member_id'])->first() : null;
        /** @var User $actor */
        $actor = Auth::user();
        $this->horses->recordUse($event, $horse, $rider, (int) $data['minutes'], $actor, $data['note'] ?? null);

        return redirect()->route('club.events.show', $event)->with('success', __('club.horses.flash.use_recorded', ['horse' => $horse->name]));
    }

    private function details(Event $event): ClubEventDetails {
        $details = $event->clubDetails;
        abort_if($details === null, 404);

        return $details;
    }
}
