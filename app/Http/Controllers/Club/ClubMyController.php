<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubAttendanceSheetStatus, ClubAttendanceStatus, ClubGuardianPermission, ClubParticipationSource, ClubParticipationStatus};
use App\Http\Controllers\Controller;
use App\Models\Club\{ClubAttendanceRecord, ClubMember, ClubNotification};
use App\Models\{Event, User};
use App\Services\Club\{ClubAttendanceService, ClubEventService, ClubPortalContext, ClubPortalSubject};
use App\Services\UI\DateRangeContext;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * „Mein Verein“ (Feature 159, MVP-845): eigene Termine mit An-/Abmeldung,
 * Änderungen, bestätigte Anwesenheit — für das verknüpfte Mitglied oder als
 * ausdrücklich gewählte Vertretung. Keine fremden Mitglieder, Kontaktdaten
 * oder Fehlzeiten. Fachlogik in ClubEventService/ClubAttendanceService.
 */
class ClubMyController extends Controller {
    public function __construct(
        private readonly ClubPortalContext $context,
        private readonly ClubEventService $clubEvents,
        private readonly ClubAttendanceService $attendance,
    ) {}

    public function index(DateRangeContext $range): View {
        Gate::authorize('club-my');
        $subject = $this->subject();
        $member = $subject->member;
        $now = CarbonImmutable::now();
        $current = $range->current();

        $upcoming = $this->clubEvents->visibleEventsFor($member, $now, $now->addDays(90));
        $recent = $this->clubEvents->visibleEventsFor($member, $now->subDays(14), $now)
            ->filter(fn(array $row): bool => $row['participation'] !== null)
            ->reverse()
            ->take(10)
            ->values();
        $changes = ClubNotification::query()
            ->where('club_member_id', $member->id)
            ->whereIn('kind', [ClubNotification::KIND_RESCHEDULED, ClubNotification::KIND_CANCELLED, ClubNotification::KIND_PROMOTED])
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->unique('dedupe_key')
            ->take(10)
            ->values();

        return view('club.my.index', [
            'subject' => $subject,
            'subjects' => $this->context->subjectsFor($this->actor()),
            'upcoming' => $upcoming,
            'recent' => $recent,
            'changes' => $changes,
            'canRegister' => $subject->allows(ClubGuardianPermission::Register),
            'canViewAttendance' => $subject->allows(ClubGuardianPermission::ViewAttendance),
            'creditedMinutes' => $this->attendance->creditableMinutes($member, $current['from'], $current['to']),
            'range' => $current,
        ]);
    }

    /** Vertretungen wählen das betreute Mitglied ausdrücklich. */
    public function select(Request $request): RedirectResponse {
        Gate::authorize('club-my');
        $data = $request->validate(['club_member_id' => ['required', 'string', 'max:64']]);
        $memberId = Sqid::decodeOrNumeric(ClubMember::class, (string) $data['club_member_id']);
        abort_if($memberId === null || $this->context->select($this->actor(), $memberId) === null, 404);

        return redirect()->route('club.my.index');
    }

    public function register(Event $event): RedirectResponse {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::Register), 403);
        $details = $event->clubDetails()->first();
        abort_if($details === null, 404);
        Gate::authorize('registerMember', [$details, $subject->member]);

        $source = $subject->isSelf() ? ClubParticipationSource::Self : ClubParticipationSource::Guardian;
        $participation = $this->clubEvents->register($event, $subject->member, $this->actor(), $source, $subject->guardian);

        return redirect()
            ->route('club.my.index')
            ->with('success', __($participation->status === ClubParticipationStatus::Waitlisted ? 'club.my.flash.waitlisted' : 'club.my.flash.registered', ['title' => $event->title]));
    }

    public function cancel(Event $event): RedirectResponse {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::Register), 403);
        $details = $event->clubDetails()->first();
        abort_if($details === null, 404);
        Gate::authorize('registerMember', [$details, $subject->member]);

        $this->clubEvents->cancelRegistration($event, $subject->member, $this->actor());

        return redirect()
            ->route('club.my.index')
            ->with('success', __('club.my.flash.cancelled', ['title' => $event->title]));
    }

    /** Eigene bestätigte Nachweise im Kopfzeilen-Zeitraum — nur Anwesenheit, keine Fehlzeiten anderer. */
    public function attendance(DateRangeContext $range): View {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::ViewAttendance), 403);
        $member = $subject->member;
        $current = $range->current();

        $records = ClubAttendanceRecord::query()
            ->where('club_member_id', $member->id)
            ->with(['event:id,title,started_at,ended_at', 'sheet:id,status'])
            ->whereHas('sheet', fn(Builder $sheet) => $sheet->where('status', ClubAttendanceSheetStatus::Confirmed->value))
            ->whereHas('event', fn(Builder $event) => $event
                ->where('started_at', '>=', $current['from']->startOfDay()->utc())
                ->where('started_at', '<', $current['to']->addDay()->startOfDay()->utc()))
            ->orderByDesc('event_id')
            ->paginate(50)
            ->withQueryString();

        return view('club.my.attendance', [
            'subject' => $subject,
            'records' => $records,
            'creditedMinutes' => $this->attendance->creditableMinutes($member, $current['from'], $current['to']),
            'creditableStatuses' => [ClubAttendanceStatus::Present, ClubAttendanceStatus::Partial],
            'range' => $current,
        ]);
    }

    private function subject(): ClubPortalSubject {
        $subject = $this->context->current($this->actor());
        abort_if($subject === null, 403);

        return $subject;
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
