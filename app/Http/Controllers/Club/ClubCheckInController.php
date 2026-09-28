<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCheckInController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Controller;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventDetails, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\{ClubAttendanceService, ClubPortalContext};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * QR-Selbst-Check-in (Feature 159, MVP-1004): Der Termincode führt angemeldete
 * Nutzer zu ihren eigenen bzw. vertretenen Mitgliedern; eingecheckt wird nur,
 * wer auf der Soll-Liste steht.
 */
class ClubCheckInController extends Controller {
    public function __construct(
        private readonly ClubPortalContext $context,
        private readonly ClubAttendanceService $attendance,
    ) {}

    public function show(Request $request, string $code): View {
        [$event, $subjects] = $this->resolve($request, $code);
        $sheet = $this->attendance->sheetFor($event);
        $roster = $this->attendance->rosterFor($sheet, $event)->keyBy('id');
        $records = $sheet->records()->get()->keyBy('club_member_id');

        return view('club.checkin.show', [
            'event' => $event,
            'code' => $code,
            'confirmed' => $sheet->isConfirmed(),
            'rows' => $subjects->map(static fn ($subject): array => [
                'member' => $subject->member,
                'expected' => $roster->has($subject->member->id),
                'record' => $records->get($subject->member->id),
            ])->values(),
        ]);
    }

    public function store(Request $request, string $code): RedirectResponse {
        [$event, $subjects] = $this->resolve($request, $code);
        $memberId = (int) Sqid::decodeOrNumeric(ClubMember::class, (string) $request->input('member'));
        $subject = $subjects->get($memberId) ?? abort(403);
        /** @var User $user */
        $user = $request->user();
        $this->attendance->selfCheckIn($event, $subject->member, $user);

        return redirect()->route('club.checkin.show', $code)->with('success', __('club.checkin.flash.done', ['name' => $subject->member->fullName()]));
    }

    /** @return array{0: Event, 1: \Illuminate\Support\Collection<int, \App\Services\Club\ClubPortalSubject>} */
    private function resolve(Request $request, string $code): array {
        /** @var ClubEventDetails $details */
        $details = ClubEventDetails::query()->where('checkin_code', $code)->with('event')->firstOrFail();
        $event = $details->event ?? abort(404);
        /** @var User $user */
        $user = $request->user();
        $subjects = $this->context->subjectsFor($user);
        abort_if($subjects->isEmpty(), 403);

        return [$event, $subjects];
    }
}
