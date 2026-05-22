<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventParticipantController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Event\ParticipantStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Services\Event\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EventParticipantController extends Controller {
    public function __construct(
        private readonly CertificateService $certificates,
    ) {
    }

    /**
     * Eigene Antwort eines Teilnehmers (accept | decline).
     */
    public function respond(Request $request, Event $event): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('view', $event);

        $data = $request->validate([
            'response' => ['required', Rule::in(['accept', 'decline'])],
        ]);

        $pivot = EventParticipant::query()
            ->where('event_id', $event->getKey())
            ->where('user_id', $auth->getKey())
            ->firstOrFail();

        $data['response'] === 'accept' ? $pivot->accept() : $pivot->decline();

        return back()->with('success', __('Antwort gespeichert.'));
    }

    public function markAttended(Request $request, Event $event, User $user): RedirectResponse {
        Gate::authorize('manageParticipants', $event);

        $pivot = EventParticipant::query()
            ->where('event_id', $event->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
        $pivot->markAttended();

        return back()->with('success', __('Teilnahme erfasst.'));
    }

    public function markNoShow(Request $request, Event $event, User $user): RedirectResponse {
        Gate::authorize('manageParticipants', $event);

        $pivot = EventParticipant::query()
            ->where('event_id', $event->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
        $pivot->markNoShow();

        return back()->with('success', __('Als „nicht erschienen" markiert.'));
    }

    public function updateStatus(Request $request, Event $event, User $user): RedirectResponse {
        Gate::authorize('manageParticipants', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ParticipantStatus::class)],
        ]);

        $pivot = EventParticipant::query()
            ->where('event_id', $event->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $pivot->forceFill(['status' => $data['status']])->save();

        return back()->with('success', __('Status aktualisiert.'));
    }

    public function issueCertificate(Request $request, Event $event, User $user): RedirectResponse {
        Gate::authorize('issueCertificate', $event);

        $data = $request->validate([
            'issued_at' => ['nullable', 'date'],
        ]);

        $this->certificates->issue(
            $event,
            $user,
            isset($data['issued_at']) ? \Illuminate\Support\Carbon::parse($data['issued_at']) : null,
        );

        return back()->with('success', __('Zertifikat ausgestellt.'));
    }
}
