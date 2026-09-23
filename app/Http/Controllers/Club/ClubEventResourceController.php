<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventResourceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Http\Requests\Club\SaveResourceBookingRequest;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventDetails, ClubMember, ClubResource, ClubResourceBooking};
use App\Models\Platform\User;
use App\Services\Club\ClubResourceService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Ressourcenbelegung je Vereinstermin (MVP-853); Rechte wie Teilnehmerverwaltung des Termins. */
class ClubEventResourceController extends Controller {
    public function __construct(
        private readonly ClubResourceService $resources,
    ) {}

    public function create(Event $event): View {
        Gate::authorize('manageParticipants', $this->details($event));
        $tz = Tz::current();

        return view('club.events._booking_dialog', [
            'event' => $event,
            'resources' => ClubResource::query()->where('is_active', true)->with('parent:id,name')->orderBy('sort_order')->orderBy('name')->get(),
            'members' => ClubMember::query()->current()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'member_no']),
            'formTz' => $tz,
            'start' => CarbonImmutable::instance($event->started_at)->setTimezone($tz)->format('Y-m-d\TH:i'),
            'end' => CarbonImmutable::instance($event->ended_at)->setTimezone($tz)->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(SaveResourceBookingRequest $request, Event $event): RedirectResponse {
        Gate::authorize('manageParticipants', $this->details($event));
        $data = $request->validated();
        $tz = Tz::isValid($data['timezone'] ?? null) && ($data['timezone'] ?? 'UTC') !== 'UTC' ? (string) $data['timezone'] : Tz::current();
        foreach (['starts_at', 'ends_at'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $data[$key] = CarbonImmutable::parse((string) $data[$key], $tz)->utc()->format('Y-m-d H:i:s');
            }
        }
        /** @var ClubResource $resource */
        $resource = ClubResource::query()->whereKey((int) $data['club_resource_id'])->firstOrFail();
        /** @var User $actor */
        $actor = Auth::user();
        $this->resources->book($event, $resource, $data, $actor);

        return redirect()->to($this->back($event))->with('success', __('club.resources.flash.booked', ['name' => $resource->name]));
    }

    public function destroy(Event $event, ClubResourceBooking $booking): RedirectResponse {
        Gate::authorize('manageParticipants', $this->details($event));
        abort_unless($booking->event_id === $event->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->resources->release($booking, $actor);

        return redirect()->to($this->back($event))->with('success', __('club.resources.flash.released'));
    }

    private function details(Event $event): ClubEventDetails {
        $details = $event->clubDetails;
        abort_if($details === null, 404);

        return $details;
    }

    /** Spieltage kehren auf die Spieltagsseite zurück, andere Termine auf die Terminseite. */
    private function back(Event $event): string {
        return $event->clubDetails?->kind === \App\Enums\Club\ClubEventKind::Match ? route('club.matches.show', $event) : route('club.events.show', $event);
    }
}
